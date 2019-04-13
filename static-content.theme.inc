<?php

/**
 * @file
 * Contains static_content.page.inc.
 *
 * Page callback for Static content entities.
 */

/**
 * Prepares variables for Static content templates.
 *
 * Default template: static_content.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_static_content(array &$variables) {
  // Fetch StaticContent Entity Object.
  $static_content = $variables['elements']['#static_content'];

  $variables['static_url'] = $static_content->getUrl(TRUE);
  $variables['width'] = $static_content->getWidth();
  $variables['height'] = $static_content->getHeight();

  $variables['#attached']['library'][] = 'sci/static-content';
  if ($static_content->isAutoheight()) {
    $variables['attributes']['class'][] = 'autoheight';
    $variables['#attached']['library'][] = 'sci/autoheight';
  }
}
