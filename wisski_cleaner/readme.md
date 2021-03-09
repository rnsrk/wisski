# Wisski Cleaner Module

## The background
This module aims at solving a common side effect of data created with WissKI: the loose triples. These occurr when editing of deleting a field with the user interface. When deleting the default behavious of WissKI is to only delete the main resource and preserve all the resources that where connected to it just as they were. When editing, it creates new individuals and connections, but maintains the previous resources and literal values in the database.

For example, a path for a person name is commonly:

Person -> has_identifier -> Identifier -> has_note -> "Literal value of person name"

If we edit the name, we do not edit the literal value at the end, but we create a new identifier object with the new value. Now the person is connected to this new identifier and the connection to the old way is erased. However, we still have an individual of class Identifier linked to the old value in our database. This behavious is a feature and not a bug, as documented in this issue:

https://www.drupal.org/project/wisski/issues/3093180 

Basically, we assume that the user always wants to delete the connection, but maybe the resource should remain in the database, as it might be linked to from somewhere else. There are ways to change this behaviour using disambiguation (see https://nodes.hypotheses.org/259), but it is not always appropriate or efficient.

## The solution of this module

Using this module we can find all individuals in our triplestore that are not connected to one of our main classes, like people, places, or some special kind of object. In each database there is usually a very limited number of classes that are the center of all connections. This module finds the individuals of any other class that are not connected by any path to an individual of one of the main classes.

## Things to consider

- The module only finds individuals in the default graph of the store.

- The module only works for one adapter at the time. We select the pathbuilder, but it is actually working directly on the adapter. 