# Mirador Block

## Mirador implementation in a block

Include the block with the normal block structure.

Select a Field ID where the url of a IIIF manifest is stored and displayed. The Mirador viewer will appear when this field has information.

## Installation

### Compile Mirador 3

You have to install Mirador 3 for this package to work. To compile the Mirador 3 javascript file, you will need to have git and [npm](https://docs.npmjs.com/downloading-and-installing-node-js-and-npm) installed. Then you can run the following commands to clone the mirador repository: 

```
git clone https://github.com/ProjectMirador/mirador.git mirador
cd mirador
npm install
npm run build
```

After that you have the following files in the dist subdirectory:
mirador.min.js  mirador.min.js.LICENSE.txt  mirador.min.js.map

### Copying Mirador into your Drupal Libraries

You need to copy the `dist` folder into your Drupal at /libraries/mirador (in the drupal root directory)

