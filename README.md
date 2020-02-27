# grav-plugin-Jsgrid
Include JsGrid simply inside Grav CMS
Inspiration from [Bootstrapper](https://github.com/getgrav/grav-plugin-bootstrapper) and [Forms](https://github.com/getgrav/grav-plugin-forms) 

# Create Jsgrid HTML Table

By the Admin interface, create a new page with Jsgrid Template.
In Expert view mode, you can add like a Form, a JsGrid.

```
title: Example
jsgrid:
    name: jsgridExample
    options:
        ...
    fields:
        ...
```
## Options availables
```
    filtering: true
    editing: true
    sorting: true
    paging: true
    autoload: true
    inserting: true
    width: '"100%"'
    height: '"400px"'
```

## Fields
Add the fields like a Form 
```
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
```

## To DO :
- Rules Managments
- Include 1 or many Jsgrid in a page
- Improve File managment
- Improve Twig processing
- Multi-languages for columns
- Improve Readme