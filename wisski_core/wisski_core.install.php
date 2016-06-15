<?php

function wisski_core_install() {

  $values = array(
    'id' => 'wisski_search',
    'plugin' => 'wisski_individual_search',
    'path' => 'wisski',
    'label' => 'Search WissKI entities',
  );
  $page = \Drupal\search\Entity\SearchPage->create($values);
  $page->save();
}