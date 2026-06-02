// تغییرات در قسمت نمایش مودال "بازی های انتخاب شده" و افزودن بخش راهنما برای دسته‌ها انجام شده است.
jQuery(function ($) {
    let page = 1;
    let loading = false;
    let selectedGames = [];
    let groupsLoaded = false; // flag to indicate if groups have been loaded
    const container = $('#ggp-gallery-grid');
    const searchBox = $('#ggp-search');
    const categoryFilter = $('#ggp-category-filter');
    const loadingText = $('#ggp-loading');
    const popupForm = $('#ggp-popup-form'); // فرم ثبت سفارش (نام، شماره تماس)
    const nameField = $('#ggp-name');
    const phoneField = $('#ggp-phone');
    const consoleType = $('#game-gallery-container').data('console');

    // بارگذاری دسته‌بندی‌ها
    $.get(ggp_ajax.ajax_url, {
        action: 'ggp_get_categories',
        console: consoleType
    }, function (res) {
        if (res.success && res.data.length > 0) {
            res.data.forEach(cat => {
                categoryFilter.append(`<option value="${cat.slug}">${cat.name} (${cat.count} بازی)</option>`);
            });
        }
    });

    function loadGames(reset = false) {
        if (groupsLoaded && !reset) return;
        if (loading) return;
        loading = true;
        loadingText.show();

        if (reset) {
            page = 1;
            groupsLoaded = false;
            container.empty();
            container.removeClass("single-grid");
            container.before(`<div id="ggp-instruction" style="text-align:center; margin:10px 0; font-family:'IRANSans', Tahoma, sans-serif; font-weight:bold;">برای انتخاب بازی بروی دسته‌ها کلیک کنید و سپس روی عکس بازی برای انتخاب کلیک کنید.</div>`);
        }

        $.post(ggp_ajax.ajax_url, {
            action: 'ggp_load_games',
            page: page,
            search: searchBox.val(),
            category: categoryFilter.val(),
            console: consoleType
        }, function (res) {
            if (res.groups) {
                let groupIndex = 0;
                if (page > 1) {
                    loading = false;
                    loadingText.hide();
                    return;
                }
                $.each(res.groups, function (groupName, games) {
                    const groupDiv = $(`
                        <div class="group-container">
                            <div class="group-title">${groupName} <span class="toggle-icon">+</span></div>
                            <div class="group-content" style="display: none;"></div>
                        </div>
                    `);
                    if (groupIndex % 2 === 0) {
                        groupDiv.find('.group-title').css('background-color', '#f0f0f0');
                    } else {
                        groupDiv.find('.group-title').css('background-color', '#d0d0d0');
                    }
                    groupIndex++;

                    const content = groupDiv.find('.group-content');
                    games.forEach(game => {
                        const selected = selectedGames.includes(game.title) ? 'checked' : '';
                        const selectedClass = selectedGames.includes(game.title) ? 'selected' : '';
                        const item = $(`
                            <div class="game-item ${selectedClass}" data-title="${game.title}">
                                <input type="checkbox" value="${game.title}" ${selected}>
                                <img src="${game.thumb}">
                                <div class="title">${game.title}</div>
                            </div>
                        `);
                        content.append(item);
                    });
                    container.append(groupDiv);
                });

                groupsLoaded = true;

                $('.group-title').off('click').on('click', function (e) {
                    e.preventDefault();
                    $('.group-title').not(this).each(function () {
                        let otherContent = $(this).next('.group-content');
                        if (otherContent.is(':visible')) {
                            otherContent.slideUp(300);
                            $(this).find('.toggle-icon').text('+');
                        }
                    });
                    const currentGroupTitle = $(this);
                    const content = currentGroupTitle.next('.group-content');
                    content.slideToggle(300, function () {
                        if ($(this).is(":visible")) {
                            $('html, body').animate(
                                { scrollTop: currentGroupTitle.offset().top },
                                300
                            );
                        }
                    });
                    const toggleIcon = currentGroupTitle.find('.toggle-icon');
                    toggleIcon.text(toggleIcon.text() === '+' ? '−' : '+');
                });
            } else if (res.games) {
                container.addClass("single-grid");
                res.games.forEach(game => {
                    const selected = selectedGames.includes(game.title) ? 'checked' : '';
                    const selectedClass = selectedGames.includes(game.title) ? 'selected' : '';
                    container.append(`
                        <div class="game-item ${selectedClass}" data-title="${game.title}">
                            <input type="checkbox" value="${game.title}" ${selected}>
                            <img src="${game.thumb}">
                            <div class="title">${game.title}</div>
                        </div>
                    `);
                });
                if (res.games.length > 0) {
                    page++;
                }
            }
            loading = false;
            loadingText.hide();
        });
    }

    loadGames(true);

    $(window).on('scroll', function () {
        if (groupsLoaded) return;
        if ($(window).scrollTop() + $(window).height() > $(document).height() * 0.7) {
            loadGames();
        }
    });

    // جستجو در همه دسته‌ها و یا دسته خاص
    searchBox.on('input', function () {
        const search = $(this).val().toLowerCase();
        const selectedCategory = categoryFilter.val();

        if (selectedCategory) {
            loadGames(true);
        } else {
            $('.group-container').each(function () {
                let hasMatch = false;
                const groupContent = $(this).find('.group-content');
                groupContent.children().each(function () {
                    const title = $(this).data('title').toLowerCase();
                    if (title.includes(search)) {
                        $(this).show();
                        hasMatch = true;
                    } else {
                        $(this).hide();
                    }
                });

                if (hasMatch) {
                    $(this).show();
                    // باز کردن خودکار گروه
                    groupContent.slideDown(300);
                    $(this).find('.toggle-icon').text('−');
                } else {
                    $(this).hide();
                    groupContent.slideUp(300);
                    $(this).find('.toggle-icon').text('+');
                }
            });
        }
    });

    categoryFilter.on('change', function () {
        loadGames(true);
    });

    function updateSelectedBtn() {
        $('#ggp-floating-selected').text('بازی های انتخاب شده (' + selectedGames.length + ')');
    }

    container.on('change', 'input[type="checkbox"]', function () {
        const title = $(this).val();
        const item = $(this).closest('.game-item');
        if ($(this).is(':checked')) {
            selectedGames.push(title);
            item.addClass('selected');
        } else {
            selectedGames = selectedGames.filter(x => x !== title);
            item.removeClass('selected');
        }
        updateSelectedBtn();
    });

    container.on('click', '.game-item img', function () {
        const item = $(this).closest('.game-item');
        const checkbox = item.find('input[type="checkbox"]');
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    });

    $('#ggp-floating-submit').on('click', function () {
        if (selectedGames.length === 0) {
            alert('لطفاً حداقل یک بازی انتخاب کنید.');
            return;
        }
        popupForm.fadeIn();
    });

    $('#ggp-floating-selected').on('click', function () {
        const selectedModal = $('#ggp-selected-popup');
        if (selectedModal.is(':visible')) {
            selectedModal.fadeOut();
        } else {
            let content = '<ul style="margin:0; padding:0; list-style-type:none;">';
            if (selectedGames.length === 0) {
                content += '<li>هیچ بازی انتخاب نشده است.</li>';
            } else {
                selectedGames.forEach(game => {
                    content += '<li>' + game + '</li>';
                });
            }
            content += '</ul>';
            if (selectedGames.length > 0) {
                content += '<div style="margin-top:10px; text-align:center;">' +
                    '<button id="go-to-register" style="padding:10px 15px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer; margin:0 5px;">ثبت سفارش و درخواست</button>' +
                    '<button id="continue-selecting" style="padding:10px 15px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer; margin:0 5px;">ادامه انتخاب</button>' +
                    '</div>';
            }
            $('#ggp-selected-popup .modal-content').html('<div style="font-size:0.9em;">' + content + '</div>');
            selectedModal.fadeIn();

            $('#go-to-register').off('click').on('click', function () {
                $('#ggp-selected-popup').fadeOut();
                $('html, body').animate(
                    { scrollTop: popupForm.offset().top },
                    500,
                    function () {
                        popupForm.fadeIn();
                    }
                );
            });
            $('#continue-selecting').off('click').on('click', function () {
                $('#ggp-selected-popup').fadeOut();
            });
        }
    });

    $('#ggp-floating-help').on('click', function () {
        const helpModal = $('#ggp-help-popup');
        const helpText = `
<strong style="display:block; text-align:center;">راهنمای انتخاب بازی</strong>
<ul style="list-style:none; padding:0; margin:10px 0; text-align:right;">
  <li style="margin-bottom:5px;">&#9679; برای انتخاب بازی‌ها، ابتدا بر روی هر دسته کلیک نمایید تا عکس‌های بازی‌های آن دسته نمایش یابند.</li>
  <li style="margin-bottom:5px;">&#9679; سپس برای انتخاب بازی مورد نظر، روی عکس آن کلیک کنید. تعداد بازی‌های انتخاب شده در پایین صفحه نمایش داده می‌شود.</li>
  <li style="margin-bottom:5px;">&#9679; پس از تکمیل انتخاب، در انتهای صفحه فرم اطلاعات، شامل مشخصات و شماره تماس خود را وارد کنید.</li>
  <li style="margin-bottom:5px;">&#9679; یک نسخه از سفارش شما به صورت فایل متنی در گوشی شما ذخیره شده و نسخه‌ای از آن برای ما ارسال خواهد شد.</li>
  <li style="margin-bottom:5px;">&#9679; برای ریختن بازی‌ها پس از ثبت سفارش، با آیدی wp101 در تلگرام پیام بفرستید.</li>
  <li style="margin-bottom:5px;">&#9679; با تشکر</li>
</ul>
        `;
        $('#ggp-help-popup .modal-content').html('<div style="font-size:0.9em; line-height:1.5; text-align:right;">' + helpText + '</div>');
        helpModal.fadeToggle();
    });

    $('#ggp-final-submit').on('click', function () {
        const name = nameField.val().trim();
        const phone = phoneField.val().trim();

        if (!name || !phone || selectedGames.length === 0) {
            alert("لطفاً نام، شماره تماس و حداقل یک بازی را وارد کنید.");
            return;
        }

        const consoleInput = document.querySelector('input[name="console"]');
        const consoleValue = consoleInput ? consoleInput.value : '';

        // ابتدا ذخیره سفارش در سیستم
        $.post(ggp_ajax.ajax_url, {
            action: 'ggp_save_order',
            name: name,
            phone: phone,
            games: selectedGames,
            console: consoleValue
        }, function (response) {
            if (!response.success) {
                const msg = response.data?.message || 'خطایی در ثبت سفارش رخ داد.';
                alert("❌ " + msg);
                return;
            }

            // ارسال به تلگرام
            $.post(ggp_ajax.ajax_url, {
                action: 'ggp_telegram',
                name: name,
                phone: phone,
                games: selectedGames,
                console: consoleValue
            });

            // دانلود فایل
            const utf8Bom = '\uFEFF';
            const gameLines = selectedGames.join('\r\n');
            const footer = `

----------------------------------------

✅ سفارش شما با موفقیت ثبت شد و این فایل در گوشی شما ذخیره گردید.

----------------------------------------
1) بازی‌های دارای زیرنویس فارسی
اگر بازی‌های انتخابی شما زیرنویس فارسی هستند، از لینک زیر سفارش دانلود را ثبت کنید:
https://101bazi.ir/product/ps4-sub-farsi-down/

----------------------------------------
2) بازی‌های بدون زیرنویس فارسی
اگر بازی‌های انتخابی شما زیرنویس فارسی ندارند، می‌توانید با استفاده از آموزش‌های سایت بازی‌ها را دانلود کنید.

برای هماهنگی/پشتیبانی، همین فایل سفارش را از یکی از راه‌های زیر ارسال کنید:

ایتا: @wp101s
https://eitaa.com/wp101s

بله: @wp101
https://ble.ir/wp101

تلگرام: @wp101
https://t.me/wp101

مشاوره تلفنی:
09124800575
`;
            const fullText = utf8Bom + gameLines + footer;
            const blob = new Blob([fullText], { type: 'text/plain;charset=utf-8' });
            const fileName = `${phone}-${name.replace(/\s+/g, '_')}.txt`;

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // نمایش پیام موفقیت و دکمه‌های جدید
            const gameCount = selectedGames.length;
            const pricePerGame = ggp_calculate_price_per_game(gameCount);
            const totalPrice = pricePerGame * gameCount;

            const successMsg = `
<div id="order-success-msg" style="margin-top:15px; padding:16px; border:2px solid #4CAF50; border-radius:10px; background:#f1f8f4; font-size:16px; line-height:2;">
  <p style="margin:0 0 15px 0; color:#2e7d32;"><strong>✅ سفارش شما با موفقیت ثبت شد!</strong></p>
  <div style="background:white; border:1px solid #ddd; border-radius:8px; padding:12px; margin-bottom:15px;">
    <p><strong>📊 خلاصه سفارش:</strong></p>
    <ul style="margin:5px 0; padding-right:20px;">
      <li>تعداد بازی: <strong>${gameCount}</strong></li>
      <li>قیمت هر بازی: <strong>${pricePerGame.toLocaleString('fa-IR')} تومان</strong></li>
      <li>قیمت کل: <strong style="color:#d32f2f;">${totalPrice.toLocaleString('fa-IR')} تومان</strong></li>
    </ul>
  </div>

  <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
    <button id="add-to-cart-btn" style="padding:12px 20px; background:#4CAF50; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold; font-size:15px;">
      🛒 افزودن به سبد خرید و تکمیل خرید
    </button>
    <button id="continue-shopping" style="padding:12px 20px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">
      🔄 ادامه خریداری
    </button>
  </div>

  <p style="margin:15px 0 0 0; font-size:14px; color:#666;">فایل سفارش شما در گوشی ذخیره شده و به تلگرام ارسال شد.</p>

  <p style="margin:0 0 10px 0;">
    <strong>اگر بازی‌های انتخابی شما دارای زیرنویس فارسی هستند:</strong><br>
    لطفاً برای ثبت سفارش دانلود، روی لینک زیر کلیک کنید:
    <br>
    <a href="https://101bazi.ir/product/ps4-sub-farsi-down/" target="_blank" rel="noopener noreferrer" style="color:#0b65c2; font-weight:700; text-decoration:underline;">
      ثبت سفارش بازی‌های زیرنویس فارسی
    </a>
  </p>

  <p style="margin:0 0 10px 0;">
    <strong>اگر بازی‌های انتخابی شما زیرنویس فارسی ندارند:</strong><br>
    پس از دانلود و نصب بازی‌ها، برای هماهنگی نهایی با ما از طریق زیر در ارتباط باشید:
  </p>

  <ul style="margin:0 0 10px 0; padding-right:18px;">
    <li style="margin-bottom:6px;">
      <strong>ایتا:</strong>
      <a href="eitaa://user?id=wp101s" target="_blank" rel="noopener noreferrer" style="color:#d93025; font-weight:700;">@wp101s</a>
      <span>(برای نسخه وب </span>
      <a href="https://eitaa.com/wp101s" target="_blank" rel="noopener noreferrer" style="color:#0b65c2; text-decoration:underline;">کلیک کنید</a>)
    </li>
    <li style="margin-bottom:6px;">
      <strong>بله:</strong>
      <a href="bale://user?username=wp101" target="_blank" rel="noopener noreferrer" style="color:#f59e0b; font-weight:700;">@wp101</a>
      <span>(برای نسخه وب </span>
      <a href="https://ble.ir/wp101" target="_blank" rel="noopener noreferrer" style="color:#0b65c2; text-decoration:underline;">کلیک کنید</a>)
    </li>
    <li style="margin-bottom:6px;">
      <strong>تلگرام:</strong>
      <a href="tg://resolve?domain=wp101" target="_blank" rel="noopener noreferrer" style="color:#0088cc; font-weight:700;">@wp101</a>
      <span>(برای نسخه وب </span>
      <a href="https://t.me/wp101" target="_blank" rel="noopener noreferrer" style="color:#0b65c2; text-decoration:underline;">کلیک کنید</a>)
    </li>
  </ul>

  <p style="margin:0;">
    <strong>📞 مشاوره تلفنی:</strong>
    <a href="tel:09124800575" style="color:#222; font-weight:700; text-decoration:underline;">09124800575</a>
  </p>
</div>`;
            $('#order-success-msg').remove();
            popupForm.after(successMsg);

            // رویداد دکمه افزودن به سبد خرید
            $('#add-to-cart-btn').off('click').on('click', function () {
                // تبدیل selectedGames به فرمت صحیح برای ارسال
                let formData = new FormData();
                formData.append('action', 'ggp_add_to_cart');
                formData.append('nonce', ggp_ajax.add_to_cart_nonce);
                formData.append('name', name);
                formData.append('phone', phone);
                formData.append('console', consoleValue);
                
                // افزودن بازی‌ها به فرمت صحیح
                selectedGames.forEach((game, index) => {
                    formData.append('games[]', game);
                });
                
                $.ajax({
                    url: ggp_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        console.log('Success response:', response);
                        if (response.success) {
                            // هدایت به صفحه checkout
                            window.location.href = response.data.checkout_url;
                        } else {
                            alert('❌ خطا: ' + (response.data?.message || 'خطایی در افزودن به سبد خرید رخ داد'));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.log('Error:', error);
                        alert('❌ خطا در ارتباط با سرور');
                    }
                });
            });

            // رویداد ادامه خریداری
            $('#continue-shopping').off('click').on('click', function () {
                location.reload();
            });

            nameField.val('');
            phoneField.val('');
            selectedGames = [];
            $('input[type="checkbox"]').prop('checked', false);
            $('.game-item').removeClass('selected');
            updateSelectedBtn();
        });
    });
});

// تابع محاسبه قیمت
function ggp_calculate_price_per_game(gameCount) {
    if (gameCount >= 31) return 30000;
    if (gameCount >= 21) return 35000;
    if (gameCount >= 11) return 40000;
    if (gameCount >= 5) return 45000;
    return 50000;
}
