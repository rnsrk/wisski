<?php

namespace Drupal\wisski_core;

/**
 * This class manages single namespaces.
 *   The functions edit and delete of single namespaces are defined here.
 */
class WisskiNamespaceManager {

    /**
     * The name of the ontology database table.
     */
    const TABLE_NAME = 'wisski_core_ontology_namespaces';

    /**
     * Database connection.
     *
     * @var Drupal\Core\Database\ConnectionInterface
     */
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    /**
     * Get all namespaces from the database.
     *
     * @return array
     *   Long name (IRI) keyed by short name
     */
    public function getAllNamespaces(): array {
        $result = $this->connection->select(self::TABLE_NAME, 'namespaces')
        ->fields('namespaces', ['short_name', 'long_name'])
        ->execute()
        ->fetchAllAssoc('short_name');

        $namespaces = [];
        foreach ($result as $namespace) {
            $namespaces[$namespace->short_name] = $namespace->long_name;
        }
        return $namespaces;
    }

    /**
     * Get the IRI for a given short name
     *
     * @param string $short_name
     *   The short name for which to return the IRI.
     *
     * @return string|NULL
     *   The IRI or NULL if not present.
     */
    public function getIRI(string $short_name): string|NULL {
        $result = $this->connection->select(self::TABLE_NAME, 'namespaces')
        ->fields('namespaces', ['long_name'])
        ->condition('short_name', $short_name)
        ->execute()
        ->fetch();

        if (!$result) {
            return NULL;
        }

        // Since we filter by primary key there can only be one result.
        return $result->long_name;
    }

    /**
     * Insert a new namespace into the namespace table.
     *
     * @param string $long_name
     *   The full IRI that should be abbreviated
     * @param string $short_name
     *   The replacement for the IRI
     */
    public function insertNamespace(string $long_name, string $short_name) {
        $this->connection->insert(self::TABLE_NAME)
        ->fields([
            'long_name' => $long_name,
            'short_name' => $short_name,
        ])
        ->execute();
    }

    /**
     * Edit a namespace
     *
     * @param string $short_name
     *   The old short name
     * @param string $new_short_name
     *   The new short name
     * @param string $new_long_name
     *   The new long name (IRI)
     */
    public function editNamespace(string $short_name, string $new_short_name, string $new_long_name = NULL) {
        $fields = ['short_name' => $new_short_name];
        if ($new_long_name) {
            $fields['long_name'] = $new_long_name;
        }
        $this->connection->update(self::TABLE_NAME)
        ->fields($fields)
        ->condition('short_name', $short_name)
        ->execute();
    }

    /**
     * Deletes a given namespace.
     *
     * @param string $short_name
     *   The short name of the namespace that should be deleted.
     */
    public function deleteNamespace(string $short_name) {
        $this->connection->delete(self::TABLE_NAME)
        ->condition('short_name', $short_name)
        ->execute();
        \Drupal::messenger()->addStatus("Namespace $short_name deleted.");
    }

    /**
     * Check if the given string is a valid short name
     *
     * @param string $short_name
     *   The short name to check
     *
     * @return bool
     *   TRUE if valid, false otherwise
     */
    public static function isValidShortName(string $short_name): bool {
        return preg_match('/^\w{1,32}$/', $short_name);
    }

    /**
     * Check if the given string is a valid long name (IRI)
     *
     * Taken from: https://stackoverflow.com/questions/1410311/regular-expression-for-url-validation-in-javascript
     *
     * @param string $long_name
     *   The long name (IRI) to check
     *
     * @return bool
     *   TRUE if valid, false otherwise
     */
    public static function isValidIRI(string $iri): bool {
        return preg_match('/(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/', $iri);
    }

}
