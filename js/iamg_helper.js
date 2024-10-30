/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */



'use strict';

console.log("IAMG Helper loaded!!");

if (IA_Designer) {
    document.body.addEventListener('pointerdown', function(event) {
       IA_Designer.deactivateInterface();
       console.log("Deactivating Interface");
    });

    //select elements of .IA_Presenter_Container  and if parent has class that begin with allign, add that class to the element
    let preamble_elements = document.querySelectorAll('.IA_Presenter_Container ');
    for (let i = 0; i < preamble_elements.length; i++) {
        let parent = preamble_elements[i].parentNode;
        let parent_classes = parent.classList;
        for (let j = 0; j < parent_classes.length; j++) {
            if (parent_classes[j].indexOf('align') === 0) {
                preamble_elements[i].classList.add(parent_classes[j]);
            }
        }
    }

}