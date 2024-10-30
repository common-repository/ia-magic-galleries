/*
 * Copyright © 2024  Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */

(function () {
    if (!window.eve_ia || !window.wp.media) {
        console.log("eve_ia not found");
        return;
    }

    console.log("Media Upload Script Loaded");

    eve_ia.on(['global', 'media', 'upload_interface'], function (process, title, button) {
        title = title || "Upload Media";
        button = button || "Use this media";

        //create media frame
        let frame = wp.media({
            title: title,
            // button: {
            //     text: button
            // },
            multiple: true, // Set to true to allow multiple files to be selected
            library: {
                uploadedTo: null // Start in 'Upload Files' tab
            },
        });

        //if full the browser has an element in fullscreen mode, exit fullscreen mode
        let fullscreenElement = document.fullscreenElement;
        if (fullscreenElement) {
            document.exitFullscreen();
            // console.log("Exiting Fullscreen", fullscreenElement);
        }

        // When an image is selected in the media frame...
        frame.on({
            'select': function () {
                // Get media attachment details from the frame state
                let selection = frame.state().get('selection');
                let attachment = selection.toJSON();
                console.log("Attachment", attachment);
                process(attachment);
            },
            'close': function () {
                if (fullscreenElement) {
                    fullscreenElement.requestFullscreen();
                }
            }
        });



        // Finally, open the media frame
        frame.open();
    });
})()