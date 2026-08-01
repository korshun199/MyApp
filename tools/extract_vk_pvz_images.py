from pathlib import Path

from playwright.sync_api import sync_playwright


URL = "https://vk.ru/pvz_dnr"
OUT_DIR = Path("pvz_dnr_images")
OUT_DIR.mkdir(exist_ok=True)


def close_auth_dialog(page):
    selectors = [
        '[aria-label*="Закрыть"]',
        '[aria-label*="закрыть"]',
        '[data-testid*="close"]',
        'button:has-text("×")',
    ]
    for selector in selectors:
        try:
            locator = page.locator(selector)
            for index in range(min(locator.count(), 5)):
                if locator.nth(index).is_visible():
                    locator.nth(index).click(timeout=1000)
                    return True
        except Exception:
            pass
    return False


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        executable_path="/usr/bin/google-chrome",
        headless=True,
        args=["--no-sandbox", "--disable-dev-shm-usage"],
    )
    context = browser.new_context(
        viewport={"width": 1440, "height": 1000},
        user_agent="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36",
    )
    page = context.new_page()
    page.goto(URL, wait_until="domcontentloaded", timeout=60000)
    page.wait_for_timeout(5000)
    close_auth_dialog(page)
    page.screenshot(path="pvz_dnr_page.png", full_page=False)
    print("Page:", page.url)
    print("Title:", page.title())
    print("Body:", page.locator("body").inner_text(timeout=10000)[:1000].replace("\\n", " | "))
    print("Scrollers:", page.evaluate("""() => [...document.querySelectorAll('*')]
        .filter(el => el.scrollHeight - el.clientHeight > 300)
        .slice(0, 12)
        .map(el => ({tag: el.tagName, id: el.id, cls: String(el.className).slice(0, 100), height: el.scrollHeight, client: el.clientHeight}))"""))

    images = {}
    unchanged_rounds = 0
    for _ in range(120):
        close_auth_dialog(page)
        current = page.locator("img").evaluate_all(
            """imgs => imgs.map(img => ({
                src: img.currentSrc || img.src || img.dataset.src || img.dataset.lazySrc,
                width: img.naturalWidth || img.width,
                height: img.naturalHeight || img.height
            })).filter(item => item.src)"""
        )
        before = len(images)
        for item in current:
            if item["width"] >= 300 and item["height"] >= 200:
                images[item["src"]] = item

        if len(images) == before:
            unchanged_rounds += 1
        else:
            unchanged_rounds = 0
        if unchanged_rounds >= 15:
            break

        page.evaluate("window.scrollBy(0, 2200)")
        page.mouse.move(700, 900)
        page.mouse.wheel(0, 2200)
        page.wait_for_timeout(3000)

    links = sorted(images)
    (OUT_DIR / "links.txt").write_text("\n".join(links) + ("\n" if links else ""), encoding="utf-8")

    print(f"Found {len(links)} large images")
    browser.close()
