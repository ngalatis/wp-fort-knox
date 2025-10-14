# The Paranoid Security Plugin

## Designed to be deployed in mu-plugins

This plugin is designed to be added to the `mu-plugins` folder of WordPress therefore it cannot be pulled directly from this repo. Only the php file must be copied.

## OK, what does it do?

This plugin will competely remove admin permissions to do any work modifying files and adding/removing/updating wordpress plugins. Moreover nobody can create admin accounts through the UI. These actions can only be performed using `wp-cli` after this plugin is enabled. 

## Why take the nuclear option?

Well, here are the facts. Clients want administrator accounts to feel like they have full control. Clients also are really careless with their credentials and they often get stolen. A common attack surface for worpdress is to get admin credentials, login as them, create as many admin users you like without no-one noticing, then install a couple of backdoor plugins that give you access to the whole system, then hack the site at your own time. 
If you can't create other admins or even manipulate any files from the UI then even if you find a way to grab the admin credentials of somebody you can't do any damage from the UI, assuming that there is no shell access with those credentials (it shouldn't) then the attacker can't do shit. 

## What if the client wants to install / update something?

I don't give a shit what they want, they can go through us if they want to do anything like that. Half the times clients install shitty plugins that do more harm than good anyways. It can also be great leverage for clients that end up not paying up their bills on time.

