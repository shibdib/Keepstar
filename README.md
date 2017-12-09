# Keepstar
EVE Online SSO Auth For Discord

https://discord.gg/wD7n6pr To discuss and for support.

Intended to be used with https://github.com/shibdib/Firetail **BUT** can be safely used standalone.

What It Does
-
This program will host a web page that members of your discord server can visit to be assigned roles based off of
corporation, alliance, and player specific roles. It will then insure the player remains in these roles and remove them 
if the players status changes (via a cron job).


Requirements
-
- PHP7 
- Webserver (Apache/NGINX/Whatever You're Comfortable With)
- A domain name is highly recommended
- An EVE Online Omega Account (Required to make an application)

Future Plans
-
- This framework could be easily expanded into a full blown auth system (srp, fleet tools, etc..) if the interest is 
there.

Known Issues
-
- Roles added manually are not removed (yet...)
- Not compatible with the old Dramiel database (yet?)

---

Credits
- 
Karbowiak for the original idea
