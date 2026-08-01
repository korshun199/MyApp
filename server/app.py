import hmac
import os
from pathlib import Path

from flask import Flask, abort, render_template, request, send_from_directory, url_for


BASE_DIR = Path(__file__).resolve().parent
app = Flask(__name__, template_folder=str(BASE_DIR / "templates"))


def check_token() -> None:
    expected = os.environ.get("MYAPP_TOKEN", "")
    supplied = request.args.get("tk", "")
    if not expected or not hmac.compare_digest(supplied, expected):
        abort(401)


@app.get("/app")
def app_page():
    check_token()
    return render_template("app.html")


@app.get("/health")
def health():
    return {"status": "ok"}


@app.get("/pvz")
def pvz_gallery():
    check_token()
    image_dir = BASE_DIR / "img_pvz"
    allowed_extensions = {".jpg", ".jpeg", ".png", ".webp"}
    images = sorted(
        path.name for path in image_dir.iterdir()
        if path.is_file() and path.suffix.lower() in allowed_extensions
    )
    return render_template("pvz.html", images=images, token=request.args.get("tk", ""))


@app.get("/pvz-image/<path:filename>")
def pvz_image(filename):
    check_token()
    return send_from_directory(BASE_DIR / "img_pvz", filename)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5050)
