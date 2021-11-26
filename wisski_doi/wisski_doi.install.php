<?php

/*
 * Install new database table 'wisski_doi'
 */

function wisski_core_schema()
{
  $schema['wisski_doi'] = array(

    'description' => 'Saves the namespaces on ontology load.',
    'fields' => array(
      'did' => array(
        'description' => 'Primarykey for DOI table',
        'type' => 'serial',
        'size' => 'normal',
        'not null' => TRUE,
      ),
      'doi' => array(
        'description' => 'The actual DOI.',
        'type' => 'varchar',
        'length' => 255,
      ),
      'vid' => array(
        'description' => 'The ID of the corresponding revision.',
        'type' => 'int',
        'size' => 'big',
        'not null' => TRUE,
      ),
    ),
    'primary key' => array('did'),
  );
}
