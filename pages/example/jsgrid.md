---
title: test
jsgrid:
    name: jsgridTest
    options:
        filtering: true
        editing: true
        sorting: true
        paging: true
        autoload: true
        inserting: true
        width: '"100%"'
        height: '"400px"'
    fields:
        name:
            label: Name
            type: text
            width: 150
        age:
            label: Age
            type: number
            width: 50
        address:
            label: Address
            type: text
            width: 200
        country:
            label: Country
            type: select
            items: countries
            valueField: id
            textField: name
        married:
            label: Married
            type: checkbox
---
# Jsgrid
