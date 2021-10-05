# Import OS to get environment variables
import os
import requests
# Get Flask and associated modules
from flask import Flask, request, render_template, jsonify

# Define the Flask app name from the filename
app = Flask(__name__)

# Get environment
env = os.environ.get('appenv', 'Development')

################
# Begin Routes #
################

# Root
@app.route('/')
def hello_world():
    target = request.args.get('target', 'World')
    resp=requests.get("https://api.quotable.io/random")
    json=resp.json()
    #payload={"author":"test","content":"test"}

    return render_template('body.html',payload=json,env=env)

# API Root
@app.route('/api/')
def api_world():
    
    resp=requests.get("https://api.quotable.io/random")
    json=resp.json()
    #payload={"author":"test","content":"test"}

    return render_template('body.html',payload=json,env=env)

# API Target

@app.route('/api/<target>')
def api_target(target):
    return jsonify(target=target,environment=env)

##############
# End Routes #
##############

# Start development webserver on $PORT (or 8080 if environment variable not set) when file ran directly)
if __name__ == "__main__":
    app.run(debug=True,host='0.0.0.0',port=int(os.environ.get('PORT', 8080)))

