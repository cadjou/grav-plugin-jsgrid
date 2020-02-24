---
form:
    name: jsgrid-add
    action: /grav/jsgrid/add
    fields:
        name:
            label: test
            type: text
        age:
            label: test
            type: text
        address:
            label: test
            type: text
        country:
            label: test
            type: text
        married:
            label: test
            type: text
    process:
        cadphp:
            form_json_response: 'p5:add'
---
# JsGrid add data