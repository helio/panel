# Email templates

Email templates are generated based on the *.mjml files in the same folder. 
[MJML](https://mjml.io/) is a templating language specificly for creating emails which
are readable by everyone in any email client.

The [mjml app](https://mjmlio.github.io/mjml-app/) is very helpful when it comes to
updating the template.

**Never update the HTML template!**

Convert instead the mjml file to html using:

```bash
$ node_modules/.bin/mjml src/templates/email/koala-farm.mjml -o src/templates/email/koala-farm.html
$ node_modules/.bin/mjml src/templates/email/helio.mjml -o src/templates/email/helio.html
```
