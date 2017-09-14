from flask import Flask, url_for
from flask import request, send_from_directory,render_template
from flask_cors import CORS
import json
from flask import jsonify
import random, os, sys


app = Flask(__name__)
app._static_folder = os.path.abspath(os.path.join(os.path.dirname(__file__), 'html'))
CORS(app)



def has_no_empty_params(rule):
    defaults = rule.defaults if rule.defaults is not None else ()
    arguments = rule.arguments if rule.arguments is not None else ()
    return len(defaults) >= len(arguments)

@app.route('/', methods=['GET'])
def index():
    return site_map()


@app.route('/site-map', methods=['GET'])
def site_map():
    links = []
    for rule in app.url_map.iter_rules():
        # Filter out rules we can't navigate to in a browser
        # and rules that require parameters
        if "GET" in rule.methods and has_no_empty_params(rule):
            url = url_for(rule.endpoint, **(rule.defaults or {}))
            if not url == '/site-map':
                links.append((url, rule.endpoint))
    # links is now a list of url, endpoint tuples
    if len(links) > 0:
        return jsonify({"success": True,"routes":links})
    else:
        return jsonify({"success": False,"routes":links})


@app.route('/get_image')
def get_image():
    # print(request.args)
    # return "Hello {}!".format(request.args[''])
    image_list = []
    jsondata = json.loads(open('earthporn.json','r').read())
    for child in jsondata['data']['children']:
        image_list.append(child['data']['preview']['images'][0]['source']['url'])

    random.shuffle(image_list)


    return jsonify({"success":True,"url":image_list[0]})
@app.route('/earththoughts')
def show_page():
	path = os.path.abspath(os.path.join(os.path.dirname(__file__), 'html','index.html'))
	return render_template("earththoughts.html")

@app.route('/get_thought')
def get_thought():
	title_list = []
	jsondata = json.loads(open('showerthoughts.json','r').read())
	for child in jsondata['data']['children']:
		title_list.append(child['data']['title'])
		
	random.shuffle(title_list)
	
	return jsonify({"success":True,"title":title_list[0]})

@app.errorhandler(404)
def page_not_found(e):
    return render_template('404.html')


if __name__ == '__main__':
    app.run(host='0.0.0.0', debug=True, port=5050)