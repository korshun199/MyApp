import hmac
import os
from pathlib import Path

from flask import Flask, abort, render_template, request


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


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5050)
