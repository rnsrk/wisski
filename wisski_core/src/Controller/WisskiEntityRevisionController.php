<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\wisski_core\WisskiStorageInterface;

class WisskiEntityRevisionController extends ControllerBase
{
  public function revisionOverview(WisskiEntityInterface $wisski_individual) {
//    return array
//    (
//      '#markup' => '<p>' . t('Hello from RevisionController') . '</p>',
//    );
;
    $account = $this->currentUser();
    $langcode = $wisski_individual->language()->getId();
    $langname = $wisski_individual->language()->getName();
    $languages = $wisski_individual->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $wisski_individual_storage = $this->entityTypeManager()->getStorage('wisski_individual');

    $type = "wisski_individual"; // $wisski_individual->getType();

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $wisski_individual->label()]) : $this->t('Revisions for %title', ['%title' => $wisski_individual->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert $type revisions") || $account->hasPermission('revert all revisions') || $account->hasPermission('administer nodes')) && $wisski_individual->access('update'));
    $delete_permission = (($account->hasPermission("delete $type revisions") || $account->hasPermission('delete all revisions') || $account->hasPermission('administer nodes')) && $wisski_individual->access('delete'));

    $rows = [];
    $default_revision = $wisski_individual->getRevisionId();
    $current_revision_displayed = FALSE;

    foreach ($this->getRevisionIds($wisski_individual, $wisski_individual_storage) as $vid) {

      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $wisski_individual_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      dpm($revision, 'revision');
      return array();

      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');

        // We treat also the latest translation-affecting revision as current
        // revision, if it was the default revision, as its values for the
        // current language will be the same of the current default revision in
        // this case.
        $is_current_revision = $vid == $default_revision || (!$current_revision_displayed && $revision->wasDefaultRevision());
        if (!$is_current_revision) {
          $link = Link::fromTextAndUrl($date, new Url('entity.node.revision', ['node' => $wisski_individual->id(), 'node_revision' => $vid]))->toString();
        }
        else {
          $link = $wisski_individual->toLink($date)->toString();
          $current_revision_displayed = TRUE;
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => ['#markup' => $revision->revision_log->value, '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        // @todo Simplify once https://www.drupal.org/node/2334319 lands.
        $this->renderer->addCacheableDependency($column['data'], $username);
        $row[] = $column;

        if ($is_current_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];

          $rows[] = [
            'data' => $row,
            'class' => ['revision-current'],
          ];
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $vid < $wisski_individual->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
              'url' => $has_translations ?
                Url::fromRoute('node.revision_revert_translation_confirm', ['node' => $wisski_individual->id(), 'node_revision' => $vid, 'langcode' => $langcode]) :
                Url::fromRoute('node.revision_revert_confirm', ['node' => $wisski_individual->id(), 'node_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('node.revision_delete_confirm', ['node' => $wisski_individual->id(), 'node_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];

          $rows[] = $row;
        }
      }
    }

    $build['node_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#attached' => [
        'library' => ['node/drupal.node.admin'],
      ],
      '#attributes' => ['class' => 'node-revision-table'],
    ];

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Gets a list of node revision IDs for a specific node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage handler.
   *
   * @return int[]
   * @return int[]
   *   Node revision IDs (in descending order).
   */
  protected function getRevisionIds(WisskiEntityInterface $wisski_individual, SqlContentEntityStorage $wisski_individual_storage) {
    $result = $wisski_individual_storage->getQuery()
      ->allRevisions()
      ->condition($wisski_individual->getEntityType()->getKey('id'), $wisski_individual->id())
      ->sort($wisski_individual->getEntityType()->getKey('revision'), 'DESC')
      ->pager(50)
      ->execute();
    return array_keys($result);
  }
}
