'use strict';
jQuery(document).ready(function ($) {
    $('.rch-cancel-download-images-button').on('click', function (e) {
        if (!confirm('Do you want to cancel download Images for all products? You can update images anytime for products by going to admin products page.')) {
            e.preventDefault();
            return false;
        }
    });
    $('.rch-empty-queue-images-button').on('click', function (e) {
        if (!confirm('Remove all images in the queue?')) {
            e.preventDefault();
            return false;
        }
    })
});
