<?php
/*
 Plugin Name: PDF Text Extractor with Zapier Integration
 Description: Automatically extracts text from uploaded PDFs and displays them on a results page. Sends the latest PDF to Zapier webhook.
 Version: 1.4
*/

require __DIR__ . '/vendor/autoload.php'; // Load Composer dependencies

use Smalot\PdfParser\Parser;

// Function to extract text from a PDF
function extract_pdf_text($pdf_file_path) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdf_file_path);
    return $pdf->getText();
}

// Hook into media uploads to process new PDFs and send to Zapier
add_action('add_attachment', function($post_id) {
    $file_path = get_attached_file($post_id); // Get the full file path
    $file_type = wp_check_filetype($file_path); // Check the file type

    if ($file_type['ext'] === 'pdf') { // Process only PDFs
        $text = extract_pdf_text($file_path); // Extract text from the PDF
        $pdf_url = wp_get_attachment_url($post_id); // Get the public URL of the PDF

        // Save the extracted text as post meta
        update_post_meta($post_id, '_extracted_text', $text);

        // Send data to Zapier webhook
        $zapier_webhook_url = 'https://hooks.zapier.com/hooks/catch/20775874/2r8ppmk/';
        $data = array(
            'pdf_title' => get_the_title($post_id),
            'pdf_url' => $pdf_url,
            'extracted_text' => $text,
        );

        $response = wp_remote_post($zapier_webhook_url, array(
            'method'    => 'POST',
            'body'      => json_encode($data),
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
        ));

        // Optional: Log the response for debugging
        if (is_wp_error($response)) {
            error_log('Zapier Webhook Error: ' . $response->get_error_message());
        } else {
            error_log('Zapier Webhook Response: ' . wp_remote_retrieve_body($response));
        }
    }
});

// Shortcode to display all uploaded PDFs with extracted text
add_shortcode('pdf_results', function() {
    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => 'application/pdf',
        'posts_per_page' => -1, // Fetch all PDFs
        'orderby' => 'date', // Order by upload date
        'order' => 'DESC',
    );

    $query = new WP_Query($args);
    $output = '<div class="pdf-results">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $pdf_title = get_the_title(); // Get PDF title
            $pdf_url = wp_get_attachment_url(get_the_ID()); // Get PDF URL
            $extracted_text = get_post_meta(get_the_ID(), '_extracted_text', true); // Get extracted text

            $output .= '<div class="pdf-item">';
            $output .= '<h3>' . esc_html($pdf_title) . '</h3>'; // PDF Title
            $output .= '<a href="' . esc_url($pdf_url) . '" target="_blank">View PDF</a>'; // PDF Link
            $output .= '<p>' . nl2br(esc_html($extracted_text)) . '</p>'; // Extracted Text
            $output .= '</div>';
        }
        wp_reset_postdata();
    } else {
        $output .= '<p>No PDFs found.</p>';
    }

    $output .= '</div>';
    return $output;
});

// Debugging: Log all attachments to check PDF paths (Optional)
add_action('wp_footer', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $all_attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
        ));
        foreach ($all_attachments as $attachment) {
            error_log('Attachment ID: ' . $attachment->ID . ' | MIME Type: ' . $attachment->post_mime_type . ' | Title: ' . $attachment->post_title);
        }
    }
});