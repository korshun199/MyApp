import hmac
import os
from pathlib import Path

from flask import Flask, abort, redirect, render_template, request, send_from_directory, url_for
from werkzeug.utils import secure_filename


BASE_DIR = Path(__file__).resolve().parent
FILES_DIR = Path(os.environ.get("MYAPP_FILES_DIR", "/home/work/html/files"))
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


@app.route("/files", methods=["GET", "POST"])
def files_page():
    check_token()
    token = request.args.get("tk", "")
    if request.method == "POST":
        uploaded = request.files.get("file")
        if uploaded and uploaded.filename:
            filename = secure_filename(uploaded.filename)
            if filename:
                FILES_DIR.mkdir(parents=True, exist_ok=True)
                uploaded.save(FILES_DIR / filename)
        return redirect(url_for("files_page", tk=token))

    files = []
    if FILES_DIR.is_dir():
        for path in FILES_DIR.iterdir():
            if not path.is_file() and not path.is_dir():
                continue
            stat = path.stat()
            files.append({
                "name": path.name,
                "bytes": stat.st_size if path.is_file() else -1,
                "size": format_file_size(stat.st_size) if path.is_file() else "каталог",
                "modified": stat.st_mtime,
                "kind": "folder" if path.is_dir() else file_kind(path.name),
                "is_dir": path.is_dir(),
            })

    sort_by = request.args.get("sort", "name")
    if sort_by == "size":
        files.sort(key=lambda item: item["bytes"], reverse=True)
    elif sort_by == "date":
        files.sort(key=lambda item: item["modified"], reverse=True)
    else:
        files.sort(key=lambda item: item["name"].lower())
    return render_template("files.html", files=files, token=token, sort_by=sort_by)


def safe_files_path(name: str) -> Path:
    root = FILES_DIR.resolve()
    target = (root / name).resolve()
    if target != root and root not in target.parents:
        abort(400)
    return target


@app.post("/files/mkdir")
def create_directory():
    check_token()
    name = secure_filename(request.form.get("name", "").strip())
    if name:
        FILES_DIR.mkdir(parents=True, exist_ok=True)
        safe_files_path(name).mkdir(exist_ok=True)
    return redirect(url_for("files_page", tk=request.args.get("tk", "")))


@app.post("/files/delete")
def delete_file_or_directory():
    check_token()
    name = request.form.get("name", "").strip()
    target = safe_files_path(name)
    if target.is_file():
        target.unlink()
    elif target.is_dir() and not any(target.iterdir()):
        target.rmdir()
    return redirect(url_for("files_page", tk=request.args.get("tk", "")))


def format_file_size(size: int) -> str:
    units = ("Б", "КБ", "МБ", "ГБ")
    value = float(size)
    for unit in units:
        if value < 1024 or unit == units[-1]:
            return f"{value:.0f} {unit}" if unit == "Б" else f"{value:.1f} {unit}"
        value /= 1024
    return f"{size} Б"


def file_kind(filename: str) -> str:
    extension = Path(filename).suffix.lower()
    if extension in {".jpg", ".jpeg", ".png", ".gif", ".webp"}:
        return "image"
    if extension in {".apk", ".aab"}:
        return "android"
    if extension in {".exe", ".msi"}:
        return "windows"
    if extension in {".py", ".js", ".java", ".html", ".css"}:
        return "code"
    if extension in {".zip", ".rar", ".7z", ".tar", ".gz"}:
        return "archive"
    if extension in {".ico", ".svg"}:
        return "icon"
    return "file"


@app.get("/file/<path:filename>")
def file_download(filename):
    check_token()
    target = safe_files_path(filename)
    if not target.is_file():
        abort(404)
    return send_from_directory(FILES_DIR, filename, as_attachment=False)


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
