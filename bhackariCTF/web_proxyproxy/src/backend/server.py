from flask import Flask
import os

app = Flask(__name__)
flag = os.environ.get("FLAG") or "flag{test_flag}"

@app.get("/")
def root():
    return "Nothing here"

@app.post("/debug")
def debug():
    return f"Here is your flag!: {flag}"

if __name__ == "__main__":
    app.run(debug=False, host='0.0.0.0', port=5000)
