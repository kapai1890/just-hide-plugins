<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 2.5 for filter "plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 2.7 for action "after_plugin_row_{$plugin_file}" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 2.9 for filter "{$screen->id}_per_page" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 3.1 for filter "network_admin_plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 */
class FilterPluginsList
{
    /** @var PluginsScreen */
    protected $screen = null;

    /**
     * How many plugins tried to show (but some remained hidden).
     *
     * @var int
     */
    protected $processedCount = 0;

    /**
     * How many plugins shown in the current tab.
     *
     * @var int
     */
    protected $shownCount = 0;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        // Current filter can break the multi-page listing. So force the
        // WP_Plugins_List_Table to show all plugins on a single page
        add_filter('plugins_per_page', [$this, 'forceSinglePage']);
        add_filter('plugins_network_per_page', [$this, 'forceSinglePage']);

        // Run after the FixPluginStatus and PluginActions
        add_filter('plugin_action_links', [$this, 'startOutputBuffering'], 30, 2);
        add_filter('network_admin_plugin_action_links', [$this, 'startOutputBuffering'], 30, 2);
    }

    /**
     * @param int $pluginsPerPage
     * @return int
     */
    public function forceSinglePage($pluginsPerPage)
    {
        return 999;
    }

    /**
     * @param array $filteredVar Plugin actions.
     * @param string $pluginName
     * @return array
     */
    public function startOutputBuffering($filteredVar, $pluginName)
    {
        // Magic starts here
        ob_start();

        // The priority must be higher than priority (10) of
        // wp_plugin_update_row(), which adds the message "There is a new
        // version of %plugin% available". See add_action() in function
        // wp_plugin_update_rows() in wp-admin/includes/update.php
        add_action("after_plugin_row_{$pluginName}", [$this, 'endOutputBuffering'], 20);

        return $filteredVar;
    }

    /**
     * @param string $pluginName
     */
    public function endOutputBuffering($pluginName)
    {
        $output = ob_get_clean();

        // Show the plugin if it's a proper plugin for current tab
        if (is_hidden_plugin($pluginName) == $this->screen->isOnTabHidden()) {
            echo $output;

            $this->shownCount++;
        }

        $this->processedCount++;

        // Show no-items message
        if ($this->processedCount == $this->screen->getPluginsCount() && $this->shownCount == 0) {
            no_items();
        }
    }
}
