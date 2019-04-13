It may not ever really be a great idea, but sometimes you just need to host some static HTML content. For instance, you may have inherited a single-page app that works perfectly and all you want to do is put a branding wrapper around it. Or you might have an Adobe Captivate HTML5 export that you want to embed in your site.

SCI lets you define static sites as entities and then reference and render those entities as iframes. You upload a complete site as a zip file and SCI finds an index.html file within the archive as the source for the iframe. The static sites themselves are stored as a hash-named directory in sites/default/files/static, but with SCI you can pretend that URL doesn't exist and just deal with the iframes.

## To get started:

1. enable the module
2. package up your static content as a zip file. The file serving as the entrypoint to that content should be named index.html
3. define a new static_content entity at /admin/structure/static_content and upload your archive
4. in your content, add an entity reference field of type "Static content" and set to display as a rendered entity


## Helpful tips:

- create a custom template for your static content with static-content--NAME.html.twig
- if you don't know the height of your content ahead of time (and there's only one page), try the "Autoheight" setting which attempt to correct the height after the iframe content loads.