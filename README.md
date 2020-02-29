# grav-plugin-Jsgrid

Include JsGrid simply inside Grav CMS
Inspiration from [Bootstrapper](https://github.com/getgrav/grav-plugin-bootstrapper) and [Forms](https://github.com/getgrav/grav-plugin-forms) 

## Installation

Installing the Create Pdf plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install jsgrid

This will install the Create Pdf plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/jsgrid`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `jsgrid`. You can find these files on [GitHub](https://github.com//grav-plugin-jsgrid) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/jsgrid
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com//grav-plugin-jsgrid/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/jsgrid/jsgrid.yaml` to `user/config/plugins/jsgrid.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```

## Usage - Create Jsgrid HTML Table

By the Admin interface, create a new page with Jsgrid Template.
In Expert view mode, you can add like a Form, a JsGrid.

```yaml
title: Example
jsgrid:
    name: jsgridExample
    options:
        ...
    fields:
        ...
```
### Options availables
```yaml
    filtering: true
    editing: true
    sorting: true
    paging: true
    autoload: true
    inserting: true
    width: '"100%"'
    height: '"400px"'
```

### Fields
Add the fields like a Form 
```yaml
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

## To Do :
- Rules Managments
- Include 1 or many Jsgrid in a page
- Improve File managment
- Improve Twig processing
- Multi-languages for columns
- Improve Readme