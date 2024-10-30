window.addEventListener("load", function () {
    console.log("In parent Style setter")
    let targets = document.querySelectorAll('[data-parent-style]');

    if (targets.length) {
        targets.forEach((el) => {
            const parent = el.parentElement;
            const parstyle = el.getAttribute("data-parent-style");
            parent.setAttribute('style', parstyle);
        })
    }

    targets = document.querySelectorAll('[data-parent-attributes]');

    if (targets.length) {
        targets.forEach((el) => {
            const parent = el.parentElement;
            const parent_attr = el.getAttribute("data-parent-attributes").split(";");
            parent_attr.forEach((atr) => {
                const atr_key_val = atr.split(":");
                if (atr_key_val[0]) {
                    parent.setAttribute(atr_key_val[0].trim(), atr_key_val[1].trim())
                }
            })
        })
    }
});

