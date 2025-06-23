import requests
import json

secret_key='91f0abc6-bb8a-4b77-8d02-99841c2c1331'
remote_ip='159.106.224.107'
# allow access to specified IP
url = 'https://10.3.1.2/admin/action/authorize.php?secret_key='+ secret_key + '&ip=' + remote_ip

# allow access to the IP of calling script
#url = 'https://10.3.1.2/admin/action/authorize.php?secret_key='+ secret_key + '&ip='

# no IP restrictions
#url = 'https://10.3.1.2/admin/action/authorize.php?secret_key='+ secret_key

response = requests.get(url)

print("Status Code", response.status_code)
if response.status_code == 200:
    # print("JSON Response ", response.json())
    auth = response.json()
    response = requests.get('https://10.3.1.2/layers/1/geojson.php?access_key=' + auth['access_key'])
    geojson = response.content
    print(geojson)
