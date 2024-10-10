/**
 * Copyright DeVeLab & DrawnCodes
 * Licensed under the Apache License, Version 2.0 (the "License");
 */
document.addEventListener('DOMContentLoaded', function () {
    const dropMenuItems = document.querySelectorAll('.px-navItem.has-dropmenu');
    dropMenuItems.forEach(function (item) {
        const link = item.querySelector('.px-navItem__link');
        link.addEventListener('click', function (event) {
            event.preventDefault();
            // Close any other open menus
            dropMenuItems.forEach(function (otherItem) {
                if (otherItem !== item && otherItem.classList.contains('is-open')) {
                    otherItem.classList.remove('is-open');
                }
            });
            item.classList.toggle('is-open');
        });
    });

    const pxToggle = document.querySelectorAll('.pxToggle');
    pxToggle.forEach(function (iToggle) {
        iToggle.addEventListener('click', function () {
            const input = iToggle.querySelector('input[type="hidden"]');
            const isOff = iToggle.classList.contains('pxToggle_off');

            if (isOff) {
                iToggle.classList.remove('pxToggle_off');
                iToggle.classList.add('pxToggle_on');
                iToggle.setAttribute('aria-checked', 'true');  // Correct aria-checked for 'on'
                input.value = 1;
            } else {
                iToggle.classList.remove('pxToggle_on');
                iToggle.classList.add('pxToggle_off');
                iToggle.setAttribute('aria-checked', 'false');  // Correct aria-checked for 'off'
                input.value = 0;
            }
        });
    });
});

