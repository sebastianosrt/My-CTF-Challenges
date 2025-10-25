from flask import Flask, jsonify, request
import ipaddress
import subprocess
from functools import wraps
import os

app = Flask(__name__)

def validate_ip(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        data = request.get_json() or {}
        ip_address = data.get('ip')
        
        if not ip_address:
            return jsonify({"error": "IP address is required"}), 400
            
        try:
            ipaddress.ip_address(ip_address)
            return f(ip_address, *args, **kwargs)
        except ValueError:
            return jsonify({"error": "Invalid IP address format"}), 400
    
    return decorated_function

@app.get("/")
def root():
    return "Nothing here"

@app.post("/debug")
@validate_ip
def ping(ip_address: str):
    try:
        os.popen(f'ping -c 1 {ip_address}')
        return jsonify({"status": "success"})    
    except subprocess.TimeoutExpired:
        return jsonify({"error": "Ping request timed out"}), 408
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(debug=False, host='0.0.0.0', port=5000)
