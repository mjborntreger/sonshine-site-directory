<?php
/**
 * Plugin Name: Sonshine Site Directory
 * Description: Export posts, pages, projects, and taxonomies (including custom ones) to CSV.
 * Version: 1.2
 * Author: SonShine Roofing
 */

add_action('admin_menu', function () {
    add_menu_page(
        'Site Directory',
        'Site Directory',
        'manage_options',
        'post-id-reference',
        'sonshine_render_post_id_reference_page',
        'dashicons-visibility',
        99
    );
});

add_action('admin_init', function () {
    if (!isset($_GET['page']) || $_GET['page'] !== 'post-id-reference' || !isset($_GET['export_type'])) return;

    $type = sanitize_text_field($_GET['export_type']);

    if ($type === 'all') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sonshine-all-urls.csv');
        $output = fopen('php://output', 'w');
        sonshine_export_all_sections_to_csv($output);
        fclose($output);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=sonshine-{$type}-export.csv");
    $output = fopen('php://output', 'w');
    sonshine_export_section_to_csv($output, $type);
    fclose($output);
    exit;
});

function sonshine_render_post_id_reference_page() {
    echo '<div class="wrap"><h1>Site Directory</h1>';

    echo '<p>
        <a class="button button-primary" href="' . admin_url('admin.php?page=post-id-reference&export_type=all') . '">Export All Tables to CSV</a>
    </p>';

    $sections = sonshine_get_export_sections();

    foreach ($sections as $section) {
        echo '<h2>' . esc_html($section['label']) . '</h2>';
        echo '<a class="button" href="' . admin_url('admin.php?page=post-id-reference&export_type=' . esc_attr($section['slug'])) . '">Export to CSV</a>';

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . ($section['type'] === 'taxonomy' ? 'Name' : 'Title') . '</th><th>Slug</th><th>URL</th></tr></thead><tbody>';

        if ($section['slug'] === 'drafts') {
            $items = get_posts(['post_type' => 'any', 'post_status' => 'draft', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            foreach ($items as $item) {
                echo '<tr><td>' . esc_html($item->ID) . '</td><td>' . esc_html($item->post_title) . '</td><td>' . esc_html($item->post_name) . '</td><td><a href="' . esc_url(get_permalink($item)) . '" target="_blank">' . esc_url(get_permalink($item)) . '</a></td></tr>';
            }
        } elseif ($section['type'] === 'post_type') {
            $items = get_posts(['post_type' => $section['slug'], 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            foreach ($items as $item) {
                echo '<tr><td>' . esc_html($item->ID) . '</td><td>' . esc_html($item->post_title) . '</td><td>' . esc_html($item->post_name) . '</td><td><a href="' . esc_url(get_permalink($item)) . '" target="_blank">' . esc_url(get_permalink($item)) . '</a></td></tr>';
            }
        } elseif ($section['type'] === 'taxonomy') {
            $items = get_terms(['taxonomy' => $section['slug'], 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
            foreach ($items as $term) {
                $url = get_term_link($term);
                if (!is_wp_error($url)) {
                    echo '<tr><td>' . esc_html($term->term_id) . '</td><td>' . esc_html($term->name) . '</td><td>' . esc_html($term->slug) . '</td><td><a href="' . esc_url($url) . '" target="_blank">' . esc_url($url) . '</a></td></tr>';
                }
            }
        }

        echo '</tbody></table><br>';
    }

    echo '</div>';
}

function sonshine_export_all_sections_to_csv($output) {
    $sections = sonshine_get_export_sections();
    foreach ($sections as $section) {
        fputcsv($output, ["### " . $section['label'] . " ###"]);
        sonshine_export_section_to_csv($output, $section['slug']);
        fputcsv($output, []);
    }
}

function sonshine_export_section_to_csv($output, $type) {
    if (in_array($type, ['page', 'post', 'project'])) {
        fputcsv($output, ['ID', 'Title', 'Slug', 'URL']);
        $posts = get_posts(['post_type' => $type, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        foreach ($posts as $post) {
            fputcsv($output, [$post->ID, $post->post_title, $post->post_name, get_permalink($post)]);
        }
    } elseif ($type === 'drafts') {
        fputcsv($output, ['ID', 'Title', 'Slug', 'URL']);
        $posts = get_posts(['post_type' => 'any', 'posts_per_page' => -1, 'post_status' => 'draft', 'orderby' => 'title', 'order' => 'ASC']);
        foreach ($posts as $post) {
            fputcsv($output, [$post->ID, $post->post_title, $post->post_name, get_permalink($post)]);
        }
    } else {
        fputcsv($output, ['ID', 'Name', 'Slug', 'URL']);
        $terms = get_terms(['taxonomy' => $type, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        foreach ($terms as $term) {
            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                fputcsv($output, [$term->term_id, $term->name, $term->slug, $link]);
            }
        }
    }
}

function sonshine_get_export_sections() {
    return [
        ['type' => 'post_type', 'slug' => 'page', 'label' => 'Pages'],
        ['type' => 'post_type', 'slug' => 'post', 'label' => 'Blog Posts'],
        ['type' => 'post_type', 'slug' => 'project', 'label' => 'Projects'],
        ['type' => 'post_type', 'slug' => 'drafts', 'label' => 'Drafts'],
        ['type' => 'taxonomy',  'slug' => 'category', 'label' => 'Categories'],
        ['type' => 'taxonomy',  'slug' => 'post_tag', 'label' => 'Tags'],
        ['type' => 'taxonomy',  'slug' => 'project_category', 'label' => 'Project Categories'],
        ['type' => 'taxonomy',  'slug' => 'material_type', 'label' => 'Material Types'],
        ['type' => 'taxonomy',  'slug' => 'roof_color', 'label' => 'Roof Colors'],
        ['type' => 'taxonomy',  'slug' => 'video_category', 'label' => 'Video Categories'],
    ];
}
?>
