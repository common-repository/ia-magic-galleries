/*
 * Copyright © 2023 Information Aesthetics. All rights reserved.
 * This work is licensed under the GPL2, V2 license.
 */


window.addEventListener("load", function(){
  if (window.IA_Presenter_loader){
    console.log("In Boot Script")
    IA_Presenter_loader(iamg_settings.settings, undefined, iamg_settings.resources);
  }
})
