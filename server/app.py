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
        files = sorted(
            path.name for path in FILES_DIR.iterdir()
            if path.is_file()
        )
    return render_template("files.html", files=files, token=token)


@app.get("/file/<path:filename>")
def file_download(filename):
    check_token()
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
