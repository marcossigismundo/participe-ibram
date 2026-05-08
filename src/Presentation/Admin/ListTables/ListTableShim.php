<?php
/**
 * Loader/shim for `WP_List_Table` in admin or test context.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

/**
 * Ensures the global class `\WP_List_Table` is available before our list
 * tables are loaded.
 *
 *  - In WordPress admin: include `wp-admin/includes/class-wp-list-table.php`
 *    (it is NOT auto-loaded on every admin screen).
 *  - Outside WordPress (unit tests): declare a minimal global shim so that
 *    `extends \WP_List_Table` does not blow up in static analysers/PHPUnit.
 */
final class ListTableShim
{
    /**
     * Idempotent guard. Subsequent calls are no-ops.
     */
    public static function ensure(): void
    {
        if (class_exists('\WP_List_Table', false)) {
            return;
        }

        if (defined('ABSPATH')) {
            $candidate = constant('ABSPATH') . 'wp-admin/includes/class-wp-list-table.php';
            if (is_string($candidate) && file_exists($candidate)) {
                require_once $candidate;
                if (class_exists('\WP_List_Table', false)) {
                    return;
                }
            }
        }

        // Fallback shim: declared in the GLOBAL namespace so `extends \WP_List_Table`
        // resolves correctly. Done via `eval` because we cannot escape the
        // current file's namespace in pure PHP.
        eval(
            'abstract class WP_List_Table {'
            . ' public $items = [];'
            . ' protected $_args = [];'
            . ' protected $_pagination_args = [];'
            . ' protected $_column_headers = [];'
            . ' public function __construct(array $args = []) { $this->_args = $args; }'
            . ' protected function set_pagination_args(array $args): void { $this->_pagination_args = $args; }'
            . ' public function get_pagenum(): int { return 1; }'
            . ' public function get_columns(): array { return []; }'
            . ' public function get_sortable_columns(): array { return []; }'
            . ' public function get_bulk_actions(): array { return []; }'
            . ' public function row_actions(array $actions, bool $always_visible = false): string { return ""; }'
            . ' public function single_row($item): void {}'
            . ' public function display(): void {}'
            . ' public function prepare_items(): void {}'
            . ' public function column_default($item, $column_name) { return ""; }'
            . ' public function column_cb($item) { return ""; }'
            . ' public function get_column_info(): array { return [[], [], []]; }'
            . ' public function search_box(string $text, string $input_id): void {}'
            . ' public function current_action() { return false; }'
            . ' public function extra_tablenav($which) {}'
            . '}'
        );
    }
}
