/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

(function () {
    // console.log("Save Monitor Script Loaded");

    let last_time = Date.now();
     setTimeout( function () {
        wp.data.subscribe(function () {
            if (!window.eve_ia) return;

            const isSavingPost = wp.data.select('core/editor').isSavingPost();
            const isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

            if (isSavingPost && !isAutosavingPost && (Date.now() - last_time > 2000)) {
                last_time = Date.now();
                eve_ia(['global', 'editor', 'save']);
            }
        })
    }, 200);

//add a listener to navigate away from the page
    window.addEventListener("beforeunload", function (e) {
        if (!window.eve_ia) return;
        eve_ia(['global', 'editor', 'save']);
    });
})()
