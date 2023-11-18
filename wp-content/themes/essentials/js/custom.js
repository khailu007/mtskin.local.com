(function($){
    "use strict";

    let mobileBreakPoint = 992;
    if (typeof pixfort_main_object === 'undefined') { window.pixfort_main_object = {}; }
    if (pixfort_main_object.hasOwnProperty('dataBreakpoint')){
        mobileBreakPoint = pixfort_main_object.dataBreakpoint;    
    }
    
    NProgress.configure({
        minimum: 0.01,
        speed: 800,
        trickleSpeed: 800,
        showSpinner: true,
        parent: 'body',
        template: '<div class="bar" role="bar"><div class="peg"></div></div>'
    });
    if(!$('body').hasClass('pix-disable-loading-bar')&&$('.pix-post-area').length==0){
        NProgress.start();
    }

    jQuery(document).ready(function($) {
        // Page transition
        var last_clicked;
        $(window).on('click', function (e) {
            last_clicked = e.target;
            return true;
        });
        $(window).on('beforeunload', function (e) {
            var e = e || window.event;
            let showTransition = true;
            if(last_clicked){
                if(last_clicked.href && last_clicked.href!= 'undefined'){
                    if(last_clicked.href.startsWith('tel')||last_clicked.href.startsWith('mailto')){
                        showTransition = false;
                    }
                }else{
                    let plink = $(last_clicked).closest('a');
                    if(plink && plink.href != undefined){
                        if(plink.href.startsWith('tel')||plink.href.startsWith('mailto')){
                            showTransition = false;
                        }
                    }
                }
            }
            if(showTransition){
                document.body.classList.remove('render');
            }else{
                document.body.classList.add('render');
            }
        });

        function addEvent(obj, evt, fn) {
            if (obj.addEventListener) {
                obj.addEventListener(evt, fn, false);
            }
            else if (obj.attachEvent) {
                obj.attachEvent("on" + evt, fn);
            }
        }

        setTimeout(() => document.body.classList.add('render'), 0);
        setTimeout(() => document.body.classList.add('pix-body-loaded'), 0);


        function loadCSS(id, href) {

          var cssLink = $("<link>");
          $("head").append(cssLink); //IE hack: append before setting href

          cssLink.attr({
            rel:  "stylesheet",
            type: "text/css",
            href: href,
            id: id + '-css'
          });

        };

        function pix_dynamic_popup_js(scripts, styles){
            Object.keys(styles).forEach(key => {
                if(!$('#'+key+'-css').length){
                    console.log(key, styles[key]);
                    loadCSS(styles[key].handle, styles[key].src);
                }
            });
            Object.keys(scripts).forEach(key => {
                if(!$('#'+key+'-js').length){
                    console.log(key, scripts[key]);
                    if(scripts[key].extra && scripts[key].extra.data){
                        $('<script>')
                        .attr('type', 'text/javascript')
                        .attr('id', scripts[key].handle+'-extra')
                        .text(scripts[key].extra.data)
                        .appendTo('head');
                    }
                    $.cachedScript(scripts[key].src);
                }
            });
        }
        function pixfort_popup(link){
            $.alert({
                title: '',
                columnClass: '',
                backgroundDismiss: true,
                buttons: false,
                theme: 'pix-main-popup',
                content: '<div></div>',
                onOpenBefore: function () {
                    var self = this;
                    self.setColumnClass('col-2 pix-popup-edit');
                    self.showLoading(true);
                },
                onContentReady: function () {
                    var self = this;
                    return $.ajax({
                        url: link,
                        method: 'get'
                    }).done(function (response) {
                        var data = false;
                        try {
                            data =  JSON.parse(response);
                        } catch (e) {
                            return false;
                        }
                        if(data){
                            if(data.html){
                                let size = data.size + ' pix-popup-edit pix-popup-ready';
                                self.setColumnClass(size);
                                self.setContentAppend( '<div class="pix-popup-content-div">' + data.html + '</div>');
                                self.hideLoading(true);
                                setTimeout(function(){
                                    self.$body.addClass('pix-popup-animate');
                                    $('.pix-intro-img img').addClass('animated');
                                    $('body').trigger('pix_popup_open');
                                    pix_animation(self.$body, true);
                                    piximations.init();
                                    pix_init_c7();
                                    pixLoadImgs();
                                    // init_pix_maps();
                                    pixLoadMaps();
                                    pix_countdown();
                                    if(self.$body.find('[data-toggle="tooltip"]').length){
                                        self.$body.find('[data-toggle="tooltip"]').tooltip();
                                    }
                                    if($('[data-toggle="tooltip"]').length){
                                        $('[data-toggle="tooltip"]').tooltip();
                                    }

                                }, 200);
                                setTimeout(function(){
                                    self.$body.find('.elementor-invisible').removeClass('elementor-invisible');
                                    if (typeof elementorFrontend !== 'undefined' && elementorFrontend !== null) {
                                        if(elementorFrontend){
                                            elementorFrontend.hooks.addAction( 'init', function() {
                                                pix_animation(false, true);
                                            } );
                                            self.$body.find(".elementor-element").each(function() {
                                				elementorFrontend.elementsHandler.runReadyTrigger( jQuery( this ) );
                                			});
                                            // fix elementor init conflict
                                            // elementorFrontend.init();
                                            pix_animation(false, true);
                                            pix_animation(self.$body, true);
                                        }
                                    }
                                    if (typeof self.$body.find('.quform-form').quform == 'function') {
                                        self.$body.find('.quform-form').quform();
                                        if (self.$body.find('.quform-recaptcha').length && window.QuformRecaptchaLoaded) {
                                            window.QuformRecaptchaLoaded();
                                        }
                                    }

                                }, 300);
                                setTimeout(function(){
                                    pix_animation_display(self.$body);
                                    if(data.footer_content){
                                        self.setContentAppend( '<div class="pix-popup-footer-div">' +data.footer_content +'</div>' );
                                    }
                                    if(data.result && data.result.scripts && data.result.styles){
                                        pix_dynamic_popup_js(data.result.scripts, data.result.styles);
                                    }
                                }, 1000);
                                self.$body.find('.wpcf7 > form').each(function () {
                                    var $form = $( this );
                                    if(wpcf7.initForm != undefined){
                                        wpcf7.initForm( $form );
                                        if ( wpcf7.cached ) {
                                            wpcf7.refill( $form );
                                        }
                                    }else{
                                        wpcf7.init($(this)[0]);
                                    }
                                });
                                self.$body.find('.pix-close-popup').on('click', function(e){
                                    e.preventDefault();
                                    self.close();
                                });
                                self.$body.find('.pix_tabs_btns').each(function(i, elem){
                                    $(elem).find('.nav-item:first a').tab('show');
                                });
                                self.$body.find('.pix-shape-dividers').each(function(){
                                    if(!$(this).hasClass('loaded')){
                                        let divider = new dividerShapes(this);
                                        divider.initPoints();
                                        $(this).addClass('loaded');
                                    }
                                });
                            }
                        }
                    }).fail(function(){
                        self.setContent('Something went wrong, please try again.');
                    });
                }
            });
        }

        window.pixOpenPopup = function(id){
            if(pixfort_main_object.hasOwnProperty('dataPopupBase')){
                let link = pixfort_main_object.dataPopupBase + '&id=' + id;
                pixfort_popup(link);
            }
        }

        if(pixfort_main_object.hasOwnProperty('dataBodyBg')){
            $('body').css({'background-color': pixfort_main_object.dataBodyBg});
        }
        if(pixfort_main_object.hasOwnProperty('dataExitPopup')){
            setTimeout(function(){
                var link = pixfort_main_object.dataExitPopup;
                var checkLink = pixfort_main_object.dataPopupCheckLink;
                var exit_opened = false;
                if(checkLink){
                    checkLink += '&exitpopup=true';
                    $.ajax({
                        url: checkLink,
                        method: 'get'
                    }).done(function (response) {
                        try {
                            var data =  JSON.parse(response);
                            if(data&&data.result){
                                if(link&&link!=''){
                                    addEvent(document, "mouseout", function(e) {
                                        if(!exit_opened){
                                            e = e ? e : window.event;
                                            var from = e.relatedTarget || e.toElement;
                                            if (!from || from.nodeName == "HTML") {
                                                exit_opened = true;
                                                pixfort_popup(link);
                                            }
                                        }
                                    });
                                }
                            }
                        } catch (e) {
                            return false;
                        }
                    });
                }
            },0);
        }

        if(pixfort_main_object.hasOwnProperty('dataAutoPopup')){
            setTimeout(function(){
                var link = pixfort_main_object.dataAutoPopup;
                var checkLink = pixfort_main_object.dataPopupCheckLink;
                var time = 1000;
                if(pixfort_main_object.hasOwnProperty('dataAutoPopupTime')){
                    time = pixfort_main_object.dataAutoPopupTime;
                }
                if(time && !isNaN(time)){
                    time = time * 1000;
                }else{
                    time = 5000;
                }
                if(checkLink){
                    checkLink += '&autopopup=true';
                    $.ajax({
                        url: checkLink,
                        method: 'get'
                    }).done(function (response) {
                        try {
                            var data =  JSON.parse(response);
                            if(data&&data.result){
                                if(link&&link!=''){
                                    setTimeout(function(){
                                        pixfort_popup(link);
                                    }, time);
                                }
                            }
                        } catch (e) {
                            return false;
                        }
                    });
                }
            },0);
        }
        if($('.pix-cookie-banner').length){
            setTimeout(function(){
                if(pixfort_main_object.hasOwnProperty('datacookiesId')){
                    let currentCookies = localStorage.getItem("pix_cookiesbanner");
                    if(currentCookies && currentCookies == pixfort_main_object.datacookiesId){
                        $('.pix-cookie-banner').addClass('pix-closed');
                        setTimeout(function(){
                            $('.pix-cookie-banner').remove();
                        }, 2500);
                    }
                }else{
                    var checkLink = pixfort_main_object.dataPopupCheckLink;
                    if(checkLink){
                        checkLink += '&cookiesbanner=true';
                        $.ajax({
                            url: checkLink,
                            method: 'get'
                        }).done(function (response) {
                            try {
                                var data = JSON.parse(response);
                                if(data){
                                    if(data.result===false){
                                        setTimeout(function(){
                                            $('.pix-cookie-banner').addClass('pix-closed');
                                        }, 2000);
                                        setTimeout(function(){
                                            $('.pix-cookie-banner').remove();
                                        }, 2500);
                                    }else{
                                        // $('.pix-cookie-banner').removeClass('pix-closed');
                                    }
                                }
                            } catch (e) {
                                return false;
                            }
                        });
                    }
                }

            },0);
        }

        // // woocommerce product preview popup
        // $('.pix-product-preview').on('click', function(e){
        //     e.preventDefault();
        //     var link = $(this).data('preview-link');
        //     $.alert({
        //         title: '',
        //         columnClass: 'col-12 col-sm-10',
        //         backgroundDismiss: true,
        //         theme: 'pix-product-popup',
        //         closeIcon: true,
        //         content: '<div></div>',
        //         onOpenBefore: function () {
        //             var self = this;
        //             self.showLoading(true);
        //         },
        //         onContentReady: function () {
        //             var self = this;
        //             return $.ajax({
        //                 url: link,
        //                 method: 'get'
        //             }).done(function (response) {
        //                 self.setContentAppend( '<div class="pix-popup-content-div">' + response + '</div>');
        //                 self.hideLoading(true);
        //                 setTimeout(function(){
        //                     self.$body.addClass('pix-popup-animate');
        //                 }, 300);
        //             }).fail(function(){
        //                 self.setContent('Something went wrong, please try again.');
        //             });
        //         }
        //     });
        // });
        $('body').on( 'click', '.flickity-slider, .flickity-button', function(e){
            pix_animation_display();
        });
        $('body').on( 'click', '.pix-popup-link', function(e){
            e.preventDefault();
            if($(this).data('popup-link')&&$(this).data('popup-link')!=''){
                var link = $(this).data('popup-link');
                pixfort_popup(link);
            }
            return false;
        });

        $('body').on( 'click', '.pix-story-popup', function(e){
            e.preventDefault();
            var stories = $(this).data('stories');
            if(stories&&stories!=''){
                var aspect = 'embed-responsive-21by9';
                var html = '';
                html += '<div class="firas2 pix-popup-content-div"><div class="pix-story-slider bg-black pix-slider-story no-dots2">';
                $.each(stories, function(i, el){
                    html += '<div class="carousel-cell p-0">';
                    html += '<img class="jarallax-img pix-fit-cover w-100 pix-opacity-8" src="'+el+'" />';
                    html += '</div>';
                });
                html += '</div>';
                html += '</div>';
                $.alert({
                    title: '',
                    columnClass: 'col-12 col-sm-6',
                    backgroundDismiss: true,
                    buttons: false,
                    theme: 'pix-video-popup',
                    content: html,
                    onOpenBefore: function () {
                        this.showLoading(true);
                    },
                    onContentReady: function(){
                        let self = this;
                        if( $('.pix-story-slider').length>0 ){
                            // pixLoadSlider(function(){
                                $('.pix-story-slider').flickity({
                                    draggable: true,
                                    adaptiveHeight: true,
                                    wrapAround: true,
                                    autoPlay: 3500,
                                    prevNextButtons: false,
                                    imagesLoaded: true,
                                    contain: true,
                                    resize: true,
                                    ready: function(){
                                        $('.pix-story-slider').flickity('resize');
                                    },
                                    on: {
                                        ready: function() {
                                            $(this).closest('.pix-story-slider').show();
                                            $(this).closest('.pix-story-slider').removeClass('d-in');
                                            $(this).removeClass('d-in');
                                            setTimeout(function(){
                                                self.$body.addClass('pix-popup-animate');
                                            }, 400);
                                            setTimeout(function(){
                                                self.hideLoading(true);
                                            }, 600);
                                        }
                                    }
                                });
                            // });
                        }
                    }
                });
            }
            return false;
        });
        $('body').on( 'click', '.pix-video-popup', function(e){
            e.preventDefault();
            if($(this).data('content')&&$(this).data('content')!=''){
                var content = $(this).data('content');
                var aspect = 'embed-responsive-21by9';
                if($(this).data('aspect')&&$(this).data('aspect')!=''){
                    aspect = $(this).data('aspect');
                }
                var html = '';
                html += '<div class="pix-video video-active">';
                html += '<div class="embed-responsive '+aspect+'">';
                html += content;
                html += '</div>';
                html += '</div>';
                $.alert({
                    title: '',
                    columnClass: 'col-12',
                    backgroundDismiss: true,
                    buttons: false,
                    theme: 'pix-video-popup',
                    content: html,
                    onContentReady: function(){
                        this.$content.find('iframe').each(function(i, elem){
                            let src = $(elem).data('src');
                            // elem.src = "";
                            $(elem).attr('src', src).click();
                            setTimeout(function(){
                                $(elem).click();
                            }, 1000);
                        });
                    }
                });
            }
            return false;
        });

        $('body').on( 'click', '.pix-audio-popup', function(e){
            e.preventDefault();
            if($(this).data('content')&&$(this).data('content')!=''){
                var content = $(this).data('content');
                var aspect = 'embed-responsive-21by9';
                if($(this).data('aspect')&&$(this).data('aspect')!=''){
                    aspect = $(this).data('aspect');
                }
                var html = '';
                html += content;
                $.alert({
                    title: '',
                    columnClass: 'col-12',
                    backgroundDismiss: true,
                    buttons: false,
                    theme: 'pix-audio-popup',
                    content: html,
                    onContentReady: function(){
                        this.$content.find('iframe').each(function(i, elem){
                            let src = $(elem).data('src');
                            $(elem).attr('src', src).click();
                            setTimeout(function(){
                                $(elem).click();
                            }, 1000);
                        });

                    }
                });
            }
            return false;
        });

        $('body').on( 'click', '.pix-search-toggle', function(e){
            e.preventDefault();
            let self = $(this);
            self.closest('.pix-search-sm-btn').toggleClass('is-opened');
            if(self.closest('.pix-search-sm-btn').hasClass('is-opened')){
                setTimeout(function(){
                    self.closest('.pix-search-sm-btn').find('input.pix-ajax-search').focus();
                }, 300);
            }

            if( window.innerWidth < mobileBreakPoint ){
                $(this).closest('.navbar').css({'overflow': 'visible'});
                $(this).closest('header.pix-header-mobile').css({'overflow': 'visible'});
                let width = $(this).closest('.pix-search-sm-btn').width();
                let left = $(this).offset().left;
                left -= 10;
                left *= -1;
                $(this).closest('.pix-search-sm-btn').find('.pix-header-floating-search').css({
                    left: left,
                    right: 'auto'
                });
            }

            return false;
        });


        // header optimisation
        let pixfort_one,
            pixfort_top,
            pixfort_main,
            pixfort_stack,
            pixfort_m_top,
            pixfort_m_main,
            pixfort_m_stack = false;
        if( $('.pix-header-transparent-parent').length ){
            pixfort_one = $('.pix-header-transparent-parent');
        }else if( $('.pix-header-boxed').length ){
            pixfort_one = $('.pix-header-boxed');
        }else{
            if( $('.pix-topbar.pix-header-desktop').length ){
                pixfort_top = $('.pix-topbar.pix-header-desktop');
            }
            if( $('.pix-header.pix-header-desktop').length ){
                pixfort_main = $('.pix-header.pix-header-desktop');
            }
            if( $('.pix-header-stack.pix-header-desktop').length ){
                pixfort_stack = $('.pix-header-stack.pix-header-desktop');
            }
        }
        if( $('.pix-topbar.pix-header-mobile').length ){
            pixfort_m_top = $('.pix-topbar.pix-header-mobile');
        }
        if( $('.pix-header.pix-header-mobile').length ){
            pixfort_m_main = $('.pix-header.pix-header-mobile');
        }
        if( $('.pix-stack-mobile').length ){
            pixfort_m_stack = $('.pix-stack-mobile');
        }
        let header_mode = 'desktop';
        if( window.innerWidth >= mobileBreakPoint ){
            if(pixfort_m_top) pixfort_m_top.remove();
            if(pixfort_m_main) pixfort_m_main.remove();
            if(pixfort_m_stack) pixfort_m_stack.remove();
        }else{
            header_mode = 'mobile';
            if(pixfort_one) pixfort_one.remove();
            if(pixfort_top) pixfort_top.remove();
            if(pixfort_main) pixfort_main.remove();
            if(pixfort_stack) pixfort_stack.remove();
        }
        $(window).resize(function(){
            if( window.innerWidth >= mobileBreakPoint ){
                if(header_mode==='mobile'){
                    header_mode = 'desktop';
                    if(pixfort_m_top) pixfort_m_top.remove();
                    if(pixfort_m_main) pixfort_m_main.remove();
                    if(pixfort_m_stack) pixfort_m_stack.remove();

                    if(pixfort_one) $('#page').prepend(pixfort_one);
                    if(pixfort_stack) $('#page').prepend(pixfort_stack);
                    if(pixfort_main) $('#page').prepend(pixfort_main);
                    if(pixfort_top) $('#page').prepend(pixfort_top);
                    pix_animation(false, true);
                }
            }else{
                if(header_mode==='desktop'){
                    header_mode = 'mobile';
                    if(pixfort_one) pixfort_one.remove();
                    if(pixfort_top) pixfort_top.remove();
                    if(pixfort_main) pixfort_main.remove();
                    if(pixfort_stack) pixfort_stack.remove();

                    if(pixfort_m_stack)  $('#page').prepend(pixfort_m_stack);
                    if(pixfort_m_main)  $('#page').prepend(pixfort_m_main);
                    if(pixfort_m_top)  $('#page').prepend(pixfort_m_top);
                    pix_animation(false, true);
                }
            }
        });



        // Misc actions
        $('body').on( 'click', '.hamburger.small-menu-toggle', function(e){
            if($(this).attr('aria-expanded')==='true'){
                $(this).removeClass('is-active');
            }else{
                $(this).addClass('is-active');
            }
        });
        $('body').on( 'click', '.hamburger.normal-menu-toggle', function(e){
            e.preventDefault();
            // $(this).closest('.navbar-nav').find('li').removeClass('d-md-flex');
            if($(this).hasClass('is-active')){
                $(this).removeClass('is-active');
                // $(this).closest('.navbar-nav').find('li').addClass('is-shown').hide(300);
                $(this).closest('.navbar-nav').find('li').removeClass('is-shown');
            }else{
                $(this).addClass('is-active');
                // $(this).closest('.navbar-nav').find('li').show(300);
                $(this).closest('.navbar-nav').find('li').addClass('is-shown');
            }
        });
        $('body').on( 'click', '.pix-tabs-btn', function(e){
            var el = $(this).closest('.pix_tabs_container');
            let $sliders = el.find('.pix-main-slider');
            $sliders.each(function(i, elem){
                $(elem).addClass('pix-tabs-slider');
                $(elem).removeClass('pix-slider-loaded');
            });
            init_portfolio($(this).closest('.pix_tabs_container'));
            setTimeout(function(){
                init_fancy_mockup(el);
                pix_main_slider(el);

            }, 500);
        });

        // Remove empty paragraph padding
        $('p:empty').each(function(i, el){
            if(!$(el).attr('class') || $(el).attr('class')===''){
                if($(el).attr('role')===undefined){
                    $(el).remove();
                }
            }
        });
        // elementor fix
        $('.animate-in, .pix-main-slider, .pix-fancy-mockup, .feature_img').closest('.elementor-invisible').removeClass('elementor-invisible');
        $('.particles-wrapper2').closest('.vc_column_container').css('z-index', '3');

        $('.entry-content2 > section, .elementor-section-wrap > section').each(function(i, elem){
            if(!$(elem).find('.sticky-top').length){
                if(
                    $(elem).find('.pix-slider').length
                    || $(elem).find('.pix-scale-in-sm').length
                    || $(elem).find('.pix-scale-in').length
                    || $(elem).find('.pix-scale-in-lg').length
                ){
                    $(elem).removeClass('vc_section_visible vc_row_visible').addClass('overflow-hidden');
                }
            }
        });
        $('body').on("click", '.dropdown-item.dropdown-toggle', function(e){
            $(this).closest('.menu-item.dropdown.nav-item').find('> .dropdown-menu').toggleClass('show');
            e.stopPropagation();
            e.preventDefault();
        });



        var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        if(isSafari){
            $('body').addClass('pix-is-safari');
            $('.pix-slider-full').closest('section:not(.overflow-visible)').removeClass('vc_section_visible').addClass('overflow-hidden');
            $('.pix-slider-full').closest('.vc_row:not(.overflow-visible)').removeClass('vc_row_visible').addClass('overflow-hidden');
            $('.pix-scene').each(function(i, el){
                $(el).closest('section:not(.overflow-visible)').removeClass('vc_section_visible').addClass('overflow-hidden');
            });
        }

        // Make sure to search suggestions
        $('.pix-small-search').closest('.vc_row').css({"z-index": 50});
        $('.pix_element_overlay').each(function(i, el){
            $(el).css({
                'border-radius': $(el).parent().css('border-radius')
            });
        });
        setTimeout(function(){
            $('.bg-video').each(function(i, elem){
                $(elem).controls = false;
                elem.controls = false;
            });
        }, 1000);

        if($('.pix-post-area').length>0){
            NProgress.configure({
                minimum: 0.0001,
                trickleRate: 0.02,
                easing: false,
                trickleSpeed: 800,
                showSpinner: false,
                parent: 'body',
                template: '<div class="bar" role="bar"><div class="peg"></div></div>'
            });
        }

        var entry_top = 0;
        var entry_height = 0;
        if($('.pix-post-area').length>0){
            entry_top = $('#pix-entry-content').offset().top;
            entry_height = $('#pix-entry-content').height();
        }

        var mainFooter = false;
        if( $('.site-footer2:not(.pix-sticky-footer)').length ){
            mainFooter = $('.site-footer2:not(.pix-sticky-footer)')[0];
        }
        var windowHeight = $(window).height();

        document.addEventListener('scroll', (e) => {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                $('.back_to_top').addClass('active');
            } else {
                $('.back_to_top').removeClass('active');
            }
            if($('.pix-post-area').length>0){
                if(document.documentElement.scrollTop>entry_top){
                    let prog = 0.0;
                    if(document.documentElement.scrollTop>(entry_top+entry_height)){
                        prog = 0.999;
                        NProgress.set(prog);
                    }else{
                        prog = (document.documentElement.scrollTop - entry_top)/entry_height;
                        NProgress.set(prog);
                    }
                }else{
                    NProgress.set(0.001);
                }
            }

            if (mainFooter) {
                var footerRect = mainFooter.getBoundingClientRect();
                if( footerRect.top < windowHeight ){
                    pix_animation_display( $('.site-footer2:not(.pix-sticky-footer)') );
                }
            }
        }, {
          passive: true
      });


        // Back to top
        var scroll_top_duration = 700,
        back_to_top = $('.back_to_top');
        back_to_top.on('click', function(e){
            e.preventDefault();
            $('body,html').animate({scrollTop: 0}, scroll_top_duration);
            return false;
        });

        // Smooth scroll
        let header_height = 0;
        if($('#masthead').length){
            header_height = $('#masthead').height()
        }
        if($('#wpadminbar').length){
            header_height += $('#wpadminbar').height();
        }
        $('body').on('click', 'a', function(event){
            let self = this;
            var link = $(this).attr('href');
            let target = $(this).attr('target');
            if(link!== 'undefined' && link != undefined){
                if( link && link.startsWith('#pix_section') ){
                    if($(link).length){
                        event.preventDefault();
                        $('body,html').animate({scrollTop: $(link).offset().top - header_height }, scroll_top_duration);
                        setTimeout(function(){
                            $(self).closest('.navbar').find('.navbar-toggler.hamburger').click();
                        }, 200);
                        return false;
                    }
                }else if ( link.indexOf("#pix_section") != -1 ) {
                    let id = link.substr(link.indexOf("#"));
                    if($(id).length){
                        event.preventDefault();
                        $('body,html').animate({scrollTop: $(id).offset().top - header_height }, scroll_top_duration);
                        setTimeout(function(){
                            $(self).closest('.navbar').find('.navbar-toggler.hamburger').click();
                        }, 200);
                        return false;
                    }else{
                        if(target=='_blank'){
                            e.preventDefault();
                            e.stopPropagation();
                            window.open(link);
                            return false;
                        }else{
                            window.location = link;
                        }
                    }
                }else if( link && link.startsWith('#pix_popup_') ){
                    event.preventDefault();
                    let id = link.replace("#pix_popup_", "");
                    if(id){
                        pixOpenPopup(id);
                    }
                    return false;
                }else if( link && link.startsWith('#pix-product-') ){
                    if($(link).length){
                        event.stopPropagation();
                        event.preventDefault();
                            $('body,html').animate({scrollTop: $(link).offset().top - header_height - 20 }, scroll_top_duration);
                        return false;
                    }
                }else if( link && link.startsWith('#pix-tab-') && !$(this).hasClass('pix-tabs-btn') ){
                    if($(link).length){
                        event.stopPropagation();
                        event.preventDefault();
                        let tabID = link.replace("#", "");
                        $('.pix-tabs-btn[aria-controls="'+tabID+'"]').click();
                        setTimeout(function(){
                            $('body,html').animate({scrollTop: $(link).offset().top - header_height - 20 }, scroll_top_duration);
                        }, 200);

                        return false;
                    }
                }else if ( link && link.indexOf("#pix-tab-") != -1 && !$(this).hasClass('pix-tabs-btn')  ) {
                    let tabID = link.substr(link.indexOf("#pix-tab-"));
                    let tabIDName = tabID.replace("#", "");
                    $('.pix-tabs-btn[aria-controls="'+tabIDName+'"]').click();
                    if($(tabID).length){
                        setTimeout(function(){
                            $('body,html').animate({scrollTop: $(tabID).offset().top - header_height - 20 }, scroll_top_duration);
                        }, 200);

                    }
                }
            }
        });


        function is_touch_device() {
          try {
            document.createEvent("TouchEvent");
            return true;
          } catch (e) {
            return false;
          }
        }
        nav_link_init();
        window.addEventListener("resize", nav_link_init);
        function nav_link_init(){
            let IS_TOUCH_DEVICE = is_touch_device();
            // let IS_MOBILE_DEVICE = false;
            // if($( window ).width()<920){
            //     IS_MOBILE_DEVICE = true;
            // }
            // $('.dropdown-toggle').dropdown();

            $('.pix-nav-link.dropdown-toggle.nav-link').unbind('click');
            $('.pix-nav-link.dropdown-toggle.nav-link, .menu-item.menu-item-has-children.dropdown.nav-item a').on('click', function (e) {
                if($(this).attr('href')){
                    let link = $(this).attr('href');
                    let target = $(this).attr('target');
                    // if(!IS_MOBILE_DEVICE){
                        if(link&&!link.startsWith('#')){
                            // if(IS_TOUCH_DEVICE){
                                if ( link.indexOf("#pix_section") == -1 ) {
                                    if(!$(this).hasClass('pix-item-clicked') && (window.innerWidth < mobileBreakPoint)){
                                        $('.pix-item-clicked').removeClass('pix-item-clicked');
                                        $(this).addClass('pix-item-clicked');
                                    }else{
                                        if(target=='_blank'){
                                            e.preventDefault();
                                            e.stopPropagation();
                                            window.open(link);
                                            return false;
                                        }else{
                                            window.location = link;
                                        }
                                    }
                                }
                            // }else{
                            //     if ( link.indexOf("#pix_section") == -1 ) {
                            //         if(target=='_blank'){
                            //             e.preventDefault();
                            //             e.stopPropagation();
                            //             window.open(link);
                            //             return false;
                            //         }else{
                            //             window.location = link;
                            //         }
                            //     }

                            // }
                        }
                    // }

                }
            });
        }



        var page_hash = location.hash.substr(1);
        if( page_hash ){
            if( page_hash.startsWith('pix_section') ){
                if($('#'+page_hash).length){
                    setTimeout(function(){
                        $('body,html').animate({scrollTop: $('#'+page_hash).offset().top - header_height }, scroll_top_duration);
                    }, 700);
                }
            }else if( page_hash.startsWith('pix-tab') ){
                if($('#'+page_hash).length){
                    setTimeout(function(){
                        $('.pix-tabs-btn[aria-controls="'+page_hash+'"]').click();
                        setTimeout(function(){
                            $('body,html').animate({scrollTop: $('#'+page_hash).offset().top - header_height - 20 }, scroll_top_duration);
                        }, 200);
                    }, 500);
                }
            }
        }

        // Fix intro loading inside VC
        setTimeout(function(){
            $('.pix-intro-img').addClass('pix-loaded');
        }, 2000);

        // Init bootstrap tooltip
        if($('[data-toggle="tooltip"]').length){
            $('[data-toggle="tooltip"]').tooltip();
        }

        // pixfort custom dropdown
        $('.pixfort-select').selectpicker({
            styleBase: 'btn dropdown-toggle btn-light bg-white shadow-sm2 font-weight-bold text-body-default text-sm'
        });
        $('.widget:not(.widget_categories) select, .wp-block-archives.wp-block-archives-dropdown select, .wp-block-categories.wp-block-categories-dropdown select').selectpicker({
            styleBase: 'pix-widget-select pix-mb-15 btn dropdown-toggle btn-light bg-white shadow-sm2 font-weight-bold text-body-default text-sm'
        });

        // $('.pixfort-shop-select').selectpicker({
        //     styleBase: 'btn dropdown-toggle btn-light bg-white shadow-sm font-weight-bold text-body-default text-sm'
        // });

        $('.widget.widget_categories select').selectpicker({
            styleBase: 'pix-widget-select pix-mb-15 btn dropdown-toggle btn-light bg-white shadow-sm2 font-weight-bold text-body-default text-sm',
            container: 'body'
        });
        $('.widget.widget_categories select').on('changed.bs.select', function (e, clickedIndex, isSelected, previousValue) {
            e.preventDefault();
            $(this).closest('form').submit();
            e.stopPropagation();
            return false;
        });

        // Video element
        $('body').on( 'click', '.video-play-btn-inline', function(e){
            e.preventDefault();
            var btn = $(this);
            var iframe = btn.closest('.pix-video').find('iframe');
            if(iframe.attr('data-src')) {
                iframe.attr('src', iframe.attr('data-src')).click();
            }
            btn.closest('.pix-video').find('video').trigger('play');
            
            btn.parent('.pix-video').addClass('video-active');
            setTimeout(function() {
                btn.parent('.pix-video').addClass('video-start');
            }, 400);
            return false;
        });


        // Header
        var header_top = 0;
        if($('#masthead').length){
            header_top = $('#masthead').offset().top;
        }
        if($('#masthead').hasClass('pix-mt-20')){
            header_top +=20;
        }

        if($('.pix-header-transparent').length>0){
            var tran_height = $('.pix-header-transparent > div').height();
            $('.pix-main-intro-placeholder').addClass('d-block w-100').height(tran_height);
        }
        if($('.pix-header-boxed').length>0){
            var tran_height = $('.pix-header-boxed > div').height();
            $('.pix-main-intro-placeholder').addClass('d-block w-100').height(tran_height);
        }

        if($('.pix-header').length>0){
            let top = $('.pix-header').height() + 20;
            if($('#wpadminbar').length>0){
                top += $('#wpadminbar').height();
            }
            if($('#masthead').length>0){
                if($('#masthead').hasClass('pix-header-box')){
                    top += 20;
                }
            }
            $('.pix-sticky-top-adjust').css({"top": top});
        }

        // Blog floating meta box (comments + likes)
        var update_meta = false;
        if($('.pix-floating-meta').length){
            if($( window ).width()>1300){
                update_meta = true;
                var blog_post = $('.post').offset().top;


                $('.pix-floating-meta').addClass('position-fixed sticky-top2').css({
                    'top': blog_post,
                    'width': "70px",
                    'margin-left': "-90px",
                });
                if( $('.post').hasClass('post-sidebar-left') ){
                    var blog_post_right = $('.post:first-of-type').offset().left+$('.post:first-of-type').width() + 20;
                    $('.pix-floating-meta').addClass('position-fixed sticky-top2').css({
                        'left': blog_post_right,
                        'margin-left': "0px",
                    });
                }

                var top_val = 20;
                if($('#masthead').length){
                    top_val += $('#masthead').height();
                }
                if($('#wpadminbar').length){
                    top_val += $('#wpadminbar').height();
                }
                var blog_post_end = $('.post').offset().top + $('.post').height();
            }
        }else{
            update_meta = false;
        }
        if($('.pix-floating-meta').length){
            $(window).resize(function(){
                if($( window ).width()<1300){
                    update_meta = false;
                    $('.pix-floating-meta').removeClass('position-fixed sticky-top2').css({
                        'top': 'auto',
                        'width': "auto",
                        'margin-left': "0px",
                    });
                }else{
                    update_meta = true;
                    if($('.post').length){
                        var blog_post = $('.post').offset().top;
                        $('.pix-floating-meta').addClass('position-fixed sticky-top2').css({
                            'top': blog_post,
                            'width': "70px",
                            'margin-left': "-90px",
                        });
                        if( $('.post').hasClass('post-sidebar-left') ){
                            var blog_post_right = $('.post:first-of-type').offset().left+$('.post:first-of-type').width() + 20;
                            $('.pix-floating-meta').addClass('position-fixed sticky-top2').css({
                                'left': blog_post_right,
                                'margin-left': "0px",
                            });
                        }
                        var top_val = 20;
                        if($('#masthead').length){
                            top_val += $('#masthead').height();
                        }
                        if($('#wpadminbar').length){
                            top_val += $('#wpadminbar').height();
                        }
                        var blog_post_end = $('.post').offset().top + $('.post').height();
                    }

                }
            });
        }

        let body_padding = 0;
        let body_padding_bottom = 0;
        if($('body').hasClass('pix-padding-style')){
             body_padding = $('body').css('padding-left');
             body_padding_bottom = $('body').css('padding-bottom');
            if(body_padding&&body_padding!=''&&body_padding!='0'&&body_padding!='0px'){
                $('.vc_row-fluid').css({
                    'padding-left': body_padding,
                    'padding-right': body_padding
                });
                $('.back_to_top').css({
                    'margin-left': body_padding,
                    'margin-right': body_padding,
                });
                if(body_padding_bottom){
                    $('.back_to_top').css({ 'margin-bottom': body_padding_bottom });
                }
            }else{
                body_padding = 0;
            }
        }




        var header_scroll_val = 50;
        var header_text_class = '',
            header_text_scroll = '';
        if($('#page').length){
            header_scroll_val = $('#page').offset().top;
            if($('.pix-topbar').length){
                header_scroll_val += $('.pix-topbar').height();
            }
        }
        if(header_scroll_val===0){
            header_scroll_val = 6;
        }
        if($('#masthead').length){
            if($('#masthead').hasClass('pix-mt-20')){
                header_scroll_val +=20;
            }
            if($('#masthead').data('text-scroll') && $('#masthead').data('text-scroll') != ''){
                header_text_class = 'text-' + $('#masthead').data('text');
                header_text_scroll = 'text-' + $('#masthead').data('text-scroll');
            }

        }

        if($('#wpadminbar').length){
            header_scroll_val += $('#wpadminbar').height();
        }

        var header_scroll_class = $('.pix-header-container-area').attr('data-scroll-class');
        var header_scroll_color = $('.pix-header-container-area').attr('data-scroll-color');
        var header_bg_class = $('.pix-header-container-area').attr('data-bg-class');
        var header_bg_color = $('.pix-header-container-area').attr('data-bg-color');


        // sticky header option
        var pix_enable_sticky = false;
        if($('.pix-is-sticky-header').length){
            pix_enable_sticky = true;
        }
        // Moible header sticky option 1
        let pix_enable_mobile_sticky = false;
        let pix_mobile_header_height = 0;
        let mobile_header_scroll_val = header_scroll_val;
        if($('.pix-mobile-header-sticky').length && $('#mobile_head').length){
            pix_enable_mobile_sticky = true;
            pix_mobile_header_height = $('#mobile_head').outerHeight();
            mobile_header_scroll_val = $('#mobile_head').offset().top;
        }
        $('.pix-header-container-area .pix-header-text').addClass(header_text_class);

        $('.pix-header-scroll-placeholder').addClass('d-none');
        $('.pix-header-scroll-placeholder').css({'height': $('.pix-header-normal').height()});


        let isScroll = false;
        document.addEventListener('scroll', (e) => {
            if(update_meta){
                if ( ($(this).scrollTop()+top_val) > blog_post ){
                    if (($(this).scrollTop()+top_val) > blog_post_end){
                        $('.pix-floating-meta').addClass('is-hidden');
                    }else{
                        $('.pix-floating-meta').css({
                            'top': top_val
                        }).removeClass('is-hidden');
                    }
                }else{
                    $('.pix-floating-meta').css({
                        'top': blog_post - $(this).scrollTop()
                    }).removeClass('is-hidden');
                }
            }



            if(pix_enable_mobile_sticky){
                if (($(this).scrollTop() > header_scroll_val)){
                    pix_mobile_header_height = $('#mobile_head').outerHeight();
                    $('#mobile_head').addClass('pix-mobile-sticky shadow');
                    $('.pix-mobile-header-sticky').height(pix_mobile_header_height);
                }else if (($(this).scrollTop() < (header_scroll_val-5))){
                    $('#mobile_head').removeClass('pix-mobile-sticky shadow');
                    $('.pix-mobile-header-sticky').height(0);
                }
            }
            if(pix_enable_sticky){

                if (($(this).scrollTop() > header_scroll_val)){

                    if(!isScroll){
                        isScroll = true;
                        if(body_padding!=0){
                            $('.pix-header-box').css('padding-left', body_padding);
                            $('.pix-header-box').css('padding-right', body_padding);
                        }
                        $('.pix-header').addClass('is-scroll');
                        if($( window ).width() > 600){
                            $('.pix-topbar').addClass('pix-hidden');
                        }
                        $('.pix-header-container-area').removeClass(header_bg_class);
                        $('.pix-header-container-area').addClass(header_scroll_class);
                        $('.pix-header-container-area').css('background', header_scroll_color);

                        $('.pix-header-container-area .pix-header-text').removeClass(header_text_class);
                        $('.pix-header-container-area .pix-header-text').addClass(header_text_scroll);

                        $('.pix-header-boxed').addClass('pix-boxed-sticky pix-scroll-shadow');
                        $('.pix-header-box:not(.pix-no-topbar)').addClass('pix-pt-20');


                        if(body_padding!=0){
                            $('.pix-header-transparent-full').css('padding-left', body_padding);
                            $('.pix-header-transparent-full').css('padding-right', body_padding);
                        }
                        $('.pix-header-transparent').addClass('pix-transparent-sticky');
                        $('.pix-header-normal').addClass('pix-normal-sticky');
                        if($('.pix-header-normal').length){
                            $('.pix-header-scroll-placeholder').removeClass('d-none');
                            $('.pix-header-scroll-placeholder').css({'height': $('.pix-header-normal').height()});
                        }
                    }

                }else if (($(this).scrollTop() < (header_scroll_val-5))){
                    isScroll = false;
                    if($('.pix-header-normal').length){
                        $('.pix-header-scroll-placeholder').addClass('d-none');
                    }

                    $('.pix-header').removeClass('is-scroll');
                    $('.pix-topbar').removeClass('pix-hidden');
                    $('.pix-header-container-area').removeClass(header_scroll_class);
                    $('.pix-header-container-area').addClass(header_bg_class);
                    $('.pix-header-container-area').css('background', "");
                    $('.pix-header-container-area').css('background', header_bg_color);


                    $('.pix-header-container-area .pix-header-text').removeClass(header_text_scroll);
                    $('.pix-header-container-area .pix-header-text').addClass(header_text_class);

                    $('.pix-header-boxed').removeClass('pix-boxed-sticky pix-scroll-shadow');
                    $('.pix-header-box:not(.pix-no-topbar)').removeClass('pix-pt-20');

                    if(body_padding!=0){
                        $('.pix-header-box').css('padding-left', '');
                        $('.pix-header-box').css('padding-right', '');
                    }

                    $('.pix-header-transparent').removeClass('pix-transparent-sticky');
                    $('.pix-header-normal').removeClass('pix-normal-sticky');
                    if(body_padding!=0){
                        $('.pix-header-transparent-full').css('padding-left', '');
                        $('.pix-header-transparent-full').css('padding-right', '');
                    }
                }
            }

        }, {
            passive: true
        });


        function dropMenuFix(){
            $('.mega-item').each(function(i, elem){
                if($(elem).hasClass('pix-mega-style-default')){
                    let areaStack= $(elem).closest('.pix-header-stack');            
                    let areaTop= $(elem).closest('.pix-topbar');            
                    if (areaStack.length||areaTop.length) {
                        let stackContainerWidth = $('.pix-header-stack > .container:first').width();
                        let leftMargin = (window.innerWidth - stackContainerWidth) / 2;
                        $(elem).find('> .dropdown-menu').each(function(i, dropdown){
                            $(dropdown).css({
                                'margin-left': 'auto'
                            });
                            let dropLeft = $(dropdown).offset().left;
                            let dropMargin = -1 * (dropLeft - leftMargin);
                            $(dropdown).css({
                            'width': stackContainerWidth,
                            'margin-left': dropMargin
                            });
                        });
                    }
                }else{
                    let el_left = $(elem).offset().left;
                    let drop = $(elem).find('>.dropdown-menu:first');
                    if(drop&&drop.length){
                        let drop_width = drop.width();
                        if(drop.hasClass('dropdown-menu-right')){
                            let r = el_left-drop_width+$(elem).width();
                            if( r < 0){
                                drop.css({
                                    'left': 0
                                });
                            }
                        }else{
                            let r = el_left+drop_width;
                            if( r > window.innerWidth){
                                drop.css({
                                    'right': 0
                                });
                            }
                        }  
                    }
                }
            });
        }
        dropMenuFix();
        window.addEventListener("resize", dropMenuFix);


        

        // Search
        setTimeout(function(){
            var elmOverlay = $('.shape-overlays')[0];
            var overlay = new ShapeOverlays(elmOverlay);
            $('.shape-overlays').addClass('d-none');
            $('.pix-overlay').addClass('d-none');
            $("body").on("click", '.pix-search-btn', function(e) {
                e.preventDefault();
                $('.shape-overlays').removeClass('d-none');
                $('.pix-overlay').removeClass('d-none');
                if(overlay){
                    if (overlay.isAnimating) {
                        return false;
                    }
                    overlay.toggle();
                    setTimeout(function(){
                        $('.pix-overlay-item').toggleClass('is-opened');
                    }, 20);
                }
                $('.pix-search-input').focus();
                return false;
            });
            $('.pix-search-close').on('click', function(e){
                e.preventDefault();
                if(overlay){
                    if (overlay.isAnimating) {
                        return false;
                    }
                    overlay.toggle();
                    $('.pix-overlay-item').toggleClass('is-opened');
                    setTimeout(function(){
                        $('.shape-overlays').addClass('d-none');
                        $('.pix-overlay').addClass('d-none');
                    }, 1000);
                }
                return false;
            });
            $(document).keyup(function(e) {
                if(overlay&&overlay.isOpened){
                    if (e.keyCode === 27) $('.pix-search-close').click();   // esc
                }
            });
        }, 0);


        if( window.innerWidth > mobileBreakPoint ){
            if($('body').hasClass('pix-sections-stack')&&!window.vc_iframe){
                if($('body').hasClass('elementor-page')){
                    $('.site-main .elementor-section-wrap > section, .site-main .elementor-section-wrap > div, .site-footer2').stack();
                }else{
                    $('.site-content section, .site-footer2').stack();
                }
                if($('.pix-cookie-banner').length){
                    pix_animation_display($('.pix-cookie-banner'), true);
                }
            }
        }else{
            $('body').removeClass('pix-sections-stack');
        }


        setTimeout(function(){
            pix_section_stack();
        }, 500);


        if( window.innerWidth > mobileBreakPoint ){
            $('.pix-sticky-footer').pixfooter();
        }else{
            $('.pix-sticky-footer').removeClass('pix-sticky-footer');
        }


        // firefox fix
        $('.pix-main-slider.clients').each(function(i, elem){
            var waypoint = new Waypoint({
                element: elem,
                offset: '100%',
                triggerOnce: true,
                handler: function() {
                    $('.pix-main-slider').flickity('resize');
                    setTimeout(function() {
                        $('.pix-main-slider').flickity('resize');
                    }, 500);
                    this.destroy();
                }
            });
        });






        // sidebar
        $('.pix-open-sidebar').on('click', function(e){
            e.preventDefault();
            $('.pix-sidebar').addClass('opened');
            return false;
        });
        $('.pix-close-sidebar').on('click', function(e){
            e.preventDefault();
            $('.pix-sidebar').removeClass('opened');
            return false;
        });


        // Banner
        $('.pix-banner-close').on('click', function(e){
            e.preventDefault();
            var link = $(this).attr('href');
            var banner = $(this).closest('.pix-banner');
            $.ajax({
                url: link,
                method: 'GET'
            }).done(function (data) {
                // banner.addClass('pix-closed');

            }).fail(function(){
                // banner.addClass('pix-closed');
            });
            banner.addClass('pix-closed');
            return false;

        });

        // Cookies bar
        $('.pix-cookies-close').on('click', function(e){
            e.preventDefault();
            var link = $(this).attr('href');
            var cookies_banner = $(this).closest('.pix-cookie-banner');
            $.ajax({
                url: link,
                method: 'GET'
            }).done(function (data) {
                // cookies_banner.addClass('pix-closed');

            }).fail(function(){
                // cookies_banner.addClass('pix-closed');
            });
            if(pixfort_main_object.hasOwnProperty('datacookiesId')){
                localStorage.setItem("pix_cookiesbanner", pixfort_main_object.datacookiesId);
                console.log("set");
            }
            cookies_banner.addClass('pix-closed');
            return false;
        });

        $('.widget_nav_menu .menu > .menu-item.menu-item-has-children > a').on('click', function(e){
            e.preventDefault();
            $(this).parent().toggleClass('active');
            $(this).parent().find('.sub-menu').slideToggle(300, 'linear');
            return false;
        });



        setTimeout(() => document.body.classList.add('render'), 0);
        setTimeout(function(){
            if($('body').hasClass(' vc_editor compose-mode')){
                return false;
            }
            piximations.init();
            pix_countdown();
            // init_pix_maps();
            pixLoadMaps();
            init_chart();
            update_collapse();
            update_numbers();
            update_masonry();
            init_bars();
            init_scroll_rotate();
            init_fancy_mockup();
            init_portfolio();
            video_element();
            pix_init_c7();
            init_tilts();
            init_Parallax();
            init_Parallax();
            pix_animation();
            pixLazy();
            $('.pix_tabs_btns').each(function(i, elem){
                $(elem).find('.nav-item:first a').tab('show');
            });
            $("body").on("click", 'a[data-toggle="pill"]', function(e) {
                $(this).closest('.pix_tabs_btns').find('.nav-link').removeClass('active');
            });
            $('.pix-contact7-form').each(function(i, elem){
                $('input[type="text"], input[type="email"], input[type="phone"], input[type="password"], textarea').each(function(i, el){
                    $(el).addClass('form-control');
                    $(el).closest('p').addClass('form-group');
                });
            });
            $('.vc_controls-out-tl').each(function(i, elem){
                if($(elem).offset().top<0){
                    $(elem).css({ top: '-17px' });
                }
            });
        }, 0);

        setTimeout(function(){
            pixLoadImgs();
        }, 3000);

        jQuery(document.body).on("post-load", function(e) {
            pix_animation();
        })

        $('.jarallax-video').each(function(){
            let src = false;
            if($(this).attr('data-pix-bg-video')){
                src = $(this).attr('data-pix-bg-video');
            }else{
                return false;
            }
            $(this).jarallax({
                speed: 0.4,
                videoSrc: src
            });
        });
        setTimeout(function(){
            $('.pix-video-elem source').each(function(){
                if($(this).parents('.navbar').length) return false;
                var sourceFile = $(this).attr("data-src");
                $(this).attr("src", sourceFile);
                var video = this.parentElement;
                video.load();
                video.play();
            });
        }, 10000);

        let navVideos = true;
        $('.navbar').hover(function(e) {
            if(navVideos){
                $(this).find('.pix-video-elem source').each(function(){
                    var sourceFile = $(this).attr("data-src");
                    $(this).attr("src", sourceFile);
                    var video = this.parentElement;
                    video.load();
                    video.play();
                });
                navVideos = false;
            }
        });
        // navbar pix-main-menu navbar-hover-drop navbar-expand-lg navbar-light d-inline-block2
        $('.intro-jarallax').jarallax({
            speed: 0.4,
            imgSize: 'object-fit',
            imgPosition: 'object-position',
        });

        /* ---------------------------------------------------------------------------
        * Pix overlay
        * --------------------------------------------------------------------------- */

        $('.pix-shape-dividers').each(function(){
            if(!$(this).hasClass('loaded')){
                let divider = new dividerShapes(this);
                divider.initPoints();
                $(this).addClass('loaded');
            }
        });

        pix_intro_bg();

        pix_init_particles();

        window.onpageshow = function(event) {
            if (event.persisted) {
                document.body.classList.add('render');
            }
        };

        $('body').addClass('pix-loaded');
        setTimeout(() => document.body.classList.add('render'), 0);
        setTimeout(() => $('.pix-loading-circ-path').remove(), 600);
        // setTimeout(function(){
        //     $('.flickity-enabled').flickity('resize')
        // }, 0);
        NProgress.done();
    });





    document.addEventListener("DOMContentLoaded", function() {
        let lazyImages = [].slice.call(document.querySelectorAll("img.pix-lazy"));
        let active = false;
        const lazyLoad = function() {
        if (active === false) {
          active = true;

          setTimeout(function() {
            lazyImages.forEach(function(lazyImage) {
              if ((lazyImage.getBoundingClientRect().top <= window.innerHeight && lazyImage.getBoundingClientRect().bottom >= 0) ) {
                lazyImage.src = lazyImage.dataset.src;
                if(lazyImage.dataset.srcset) lazyImage.srcset = lazyImage.dataset.srcset;
                lazyImage.classList.remove("pix-lazy");

                lazyImages = lazyImages.filter(function(image) {
                  return image !== lazyImage;
                });

                if (lazyImages.length === 0) {
                  document.removeEventListener("scroll", lazyLoad);
                  window.removeEventListener("resize", lazyLoad);
                  window.removeEventListener("orientationchange", lazyLoad);
                }
              }
            });


            active = false;
          }, 200);
        }
    };
    window.pixLazy = function(){
        lazyLoad();
    }

    document.addEventListener("scroll", lazyLoad);
    window.addEventListener("resize", lazyLoad);
    window.addEventListener("orientationchange", lazyLoad);
});


    window.pixLoadImgs = function(){
        let lazyImages = [].slice.call(document.querySelectorAll("img.pix-lazy"));
        lazyImages.forEach(function(lazyImage) {
            lazyImage.src = lazyImage.dataset.src;
            lazyImage.classList.remove("pix-lazy");
            lazyImages = lazyImages.filter(function(image) {
              return image !== lazyImage;
            });
        });
    }

    window.pixInitJs = async function(el){
        if($('body').hasClass('vc_editor')){
            piximations.init();
            if(!el){
                el = $('body');
            }
            el.find('[data-toggle="tooltip"]').tooltip();
            // destroy_Parallax();
            // init_Parallax();
            $('.vc_controls-out-tl').each(function(i, elem){
                if($(elem).offset().top<0){
                    $(elem).css({ top: '-17px' });
                }
            });
            el.find('.pix-contact7-form').each(function(i, elem){
                $('input[type="text"], input[type="email"], input[type="phone"], input[type="password"], textarea').each(function(i, el){
                    $(el).addClass('form-control');
                    $(el).closest('p').addClass('form-group');
                });
            });
            el.find('.pix-shape-dividers').each(function(){
                if(!$(this).hasClass('loaded')){
                    let divider = new dividerShapes(this);
                    divider.initPoints();
                    $(this).addClass('loaded');
                }
            });
            pix_intro_bg();
        }
    }

    window.pix_init_particles = async function(){
        if( window.innerWidth < 600 ){
            pix_particles_test();
        }
        $(window).resize(function(){
            if( window.innerWidth < 600 ){
                pix_particles_test();
            }else{
                $('.pix-scene').css('display', 'block');
            }
        });
    }
    function pix_particles_test(){
        $('.pix-scene').each(function(i, elem){
            if( $(elem).find('.pix-scene-elm-res:not(.pix-particle-sm-hide)').length == 0 ){
                $(elem).css('display', 'none');
            }else{
                $(elem).css('display', 'block');
            }
        });
    }

    async function pix_intro_bg(){
        $('.pix-intro-1 .pix-intro-img img').each(function(i, elem){
            var self = this;
            var waypoint = new Waypoint({
                element: elem,
                offset: '100%',
                triggerOnce: true,
                handler		: function(){
                    setTimeout(function(){
                        $(self).addClass('animated');
                    }, 10);
                    setTimeout(function(){
                        $(self).addClass('slow-transition');
                    }, 1000);
                }
            });
        });
    }

    window.pix_cb_fn = async function(cb){
        setTimeout(cb, 0);
    }
    window.pix_init_c7 = async function(){
        $('.pix-contact7-form').each(function(i, elem){
            $('input[type="text"], input[type="email"], input[type="phone"], input[type="password"], select, textarea').each(function(i, el){
                $(el).addClass('form-control');
                $(el).closest('p').addClass('form-group');
            });
        });
    }

    window.update_masonry = async function(el){
        if(!el){
            el = $('body');
        }
        if(el.find('.pix_masonry').length){
            window.pixLoadIsotope(function(){
                el.find('.pix_masonry').each(function(i, elem){
                    setTimeout(function(){
                        $(elem).isotope({
                            itemSelector: '.grid-item',
                            percentPosition: true,
                            resize: true,
                            masonry: {
                                columnWidth: '.grid-sizer',
                                gutter: '.gutter-sizer'
                            }
                        });
                    }, 200);
                    setTimeout(function(){
                        $(elem).isotope( 'reloadItems' );
                        // pix_animation($(elem), true);
                        pix_animation(false, true);
                    }, 1900);
                });
            });
        }
    }
    window.init_fancy_mockup = async function(el){
        if(!el){
            el = $('body');
        }
        el.find('.pix-fancy-mockup').each(function(i, elem){
            var el_mockup = $(elem).find('.pix-fancy-device-img');
            var el_content = $(elem).find('.pix-fancy-content img');
            var e_top = $(elem).offset().top ;
            var range = $(elem).outerHeight();
            var rect = elem.getBoundingClientRect();
            var range_start = $(window).height();
            var range_end = $(window).height()/4;
            var range_total = range_start - range_end;
            var w_top = $(window).scrollTop() + $( window ).height();
            var percent = (rect.top - range_end) / range_total;
            var rot_per = 80*percent;
            var trans_per = 100* percent;
            var scale_per = 0.9 + (0.1*(1-percent));
            el_content.css({
                "transform": 'translate3d(0px, '+trans_per+'px, 0px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
            });
            el_mockup.css({
                "transform": 'translate3d(0px, '+trans_per+'px, 2px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
            });
            document.addEventListener('scroll', (e) => {
                rect = elem.getBoundingClientRect();
                if(rect.top <= range_start && rect.top >= range_end){
                    var percent = (rect.top - range_end) / range_total;
                    var rot_per = 80*percent;
                    var trans_per = 100* percent;
                    var scale_per = 0.9 + (0.1*(1-percent));
                    el_content.css({
                        "transform": 'translate3d(0px, '+trans_per+'px, 0px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, '+trans_per+'px, 0px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                    el_mockup.css({
                        "transform": 'translate3d(0px, '+trans_per+'px, 2px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, '+trans_per+'px, 2px) scale3d(1, '+scale_per+', 1) rotateX('+rot_per+'deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                }else if (rect.top > range_start) {
                    el_content.css({
                        "transform": 'translate3d(0px, 100px, 0px) scale3d(1, 0.9, 1) rotateX(80deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, 100px, 0px) scale3d(1, 0.9, 1) rotateX(80deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                    el_mockup.css({
                        "transform": 'translate3d(0px, 100px, 2px) scale3d(1, 0.9, 1) rotateX(80deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, 100px, 2px) scale3d(1, 0.9, 1) rotateX(80deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                }else{
                    el_content.css({
                        "transform": 'translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                    el_mockup.css({
                        "transform": 'translate3d(0px, 0px, 2px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)',
						"-webkit-transform": 'translate3d(0px, 0px, 2px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg)'
                    });
                }
            }, {
              passive: true
            });
        });

    }


    /* ---------------------------------------------------------------------------
    * Portfolio
    * --------------------------------------------------------------------------- */
    window.init_portfolio = async function(el){
        if($('.portfolio_grid').length || $('.portfolio_filter').length){
            window.pixLoadIsotope(function(){
                if(!el){
                    el = $('body');
                }
                $('.portfolio_grid').isotope({
                    // options
                    itemSelector: '.grid-item',
                    resize: true,
                    packery: {
                        gutter: 0
                    },
                });
                $('.portfolio_filter').click(function(e){
                    e.preventDefault();
                    var el = $(this);
                    var filter = el.data('category');
                    var portfolio = el.closest('.pix-portfolio').find('.portfolio_grid');
                    $(this).closest('.pix-portfolio-nav').find('.portfolio_filter').removeClass( 'is-checked' );
                    $(this).addClass( 'is-checked' );
                    portfolio.isotope({
                        // options
                        itemSelector: '.grid-item',
                        // layoutMode: 'fitRows',
                        filter: filter
                    });
                    window.pix_animation_display( portfolio );
                    return false;
                });
                setTimeout(function() {
                    window.pix_animation( $('.portfolio_grid'), true );
                }, 1500);
            });
        }
    }

    /* ---------------------------------------------------------------------------
    * Elements Parallax
    * --------------------------------------------------------------------------- */
    window.pixParallax = [];
    window.init_Parallax = async function(){
        if($('body').hasClass('vc_editor')){
            if(typeof window.vc != 'undefined'){
                if(!window.vc.loaded){
                    return false;
                }
            }else{
                return false;
            }
        }
        $('.scene').each(function(){
            var parallaxInstance = new Parallax(this, {
                relativeInput: true
            });
            window.pixParallax.push(parallaxInstance);
        });
        $('.pix-scene').each(function(){
            var depth = $(this).find('.pix-scene-particle').attr('data-pix-depth');
            var parallaxInstance = new Parallax(this, {
                relativeInput: true,
                friction: (0.2, 0.2)
            });
            window.pixParallax.push(parallaxInstance);
        });
    }

    window.destroy_Parallax = async function(){
        window.pixParallax.forEach(function(item){
            item.destroy();
            var index = window.pixParallax.indexOf(item);
            if (index > -1) {
                window.pixParallax.splice(index, 1);
            }
        });
    }

    window.init_tilts = async function(el){
        if(!el){
            el = $('body');
        }
        var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        if(!isSafari){
            el.find('.tilt').each(function(i, elem){
                $(elem).universalTilt({
                    base: window,
                    reset: true,
                    scale: 1.04,
                    reverse: false,
                    max: 15,
                    perspective: 3000,
                    speed: 4000
                });
            });
            el.find('.tilt_small').each(function(i, elem){
                $(elem).universalTilt({
                    reset: true,
                    scale: 1.01,
                    reverse: false,
                    max: 15,
                    perspective: 5000,
                    speed: 4000
                });
            });
            el.find('.tilt_big').each(function(i, elem){
                $(elem).universalTilt({
                    reset: true,
                    scale: 1.07,
                    reverse: false,
                    max: 15,
                    perspective: 1000,
                    speed: 4000
                });
            });
        }
    }

    window.update_collapse = async function(){
        $('.collapse').each(function(i, elem){
            var parent = $(elem).closest('.accordion');
            if(parent.attr('id') && parent.attr('id')!=''){
                $(elem).attr('data-parent', '#'+ parent.attr('id'));
            }
        });
    }

    window.init_scroll_rotate = async function(el){
        if(!el){
            el = $('body');
        }
        el.find('.pix-rotate-scroll').each(function(){
            var el = $(this);
            var speed = el.attr('data-speed');
            if(!speed||speed==''){
                speed = 300;
            }
            document.addEventListener('scroll', (e) => {
                var theta = $(window).scrollTop() / speed ;
                var rotationStr = "rotate(" + theta + "rad)";
                el.css({
                    "-webkit-transform": rotationStr,
                    "-moz-transform": rotationStr,
                    "transform": rotationStr
                });
            }, {
                passive: true
            });
        });
    }

    window.video_element = async function(el){
        if(!el){
            el = $('body');
        }
        el.find('video.pix-video-bg-element').each(function(i, elem){
            var that = this;
            var waypoint = new Waypoint({
                element: elem,
                offset: '100%',
                triggerOnce: true,
                handler		: function(){
                    if (that.paused) that.play();
                    this.destroy();
                }
            });
        });
    }
    window.init_bars = async function(){
        var delay = 500;
        $(".pix-progress:not(.pix_ready)").each(function(i, elem) {
            var that = this;
            $(elem).addClass('pix_ready');
            var waypoint = new Waypoint({
                element: elem,
                offset: '100%',
                triggerOnce: true,
                handler		: function(){
                    var duration = 1000;
                    var bar = $(elem).find('.progress-bar');
                    $(bar).animate({
                        width: $(bar).attr('aria-valuenow') + '%'
                    }, duration);

                    var el = $(elem).find('.pix-progress-counter');
                    var counter = 0;
                    if(el.attr('data-counter')&&el.attr('data-counter')!=''){
                        counter = Math.floor(el.attr('data-counter'));
                    }
                    $({property:0}).animate({property:counter}, {
                        duration	: duration+600,
                        easing		:'swing',
                        step		: function() {
                            el.text(Math.floor(this.property)+ '%');
                        },
                        complete	: function() {
                            el.text(this.property+ '%');
                        }
                    });
                    this.destroy();
                }
            });
        });
    }

    /* ---------------------------------------------------------------------------
    * Animate Math [counter, numbers, etc.]
    * --------------------------------------------------------------------------- */
    window.update_numbers = async function(){
        $('.animate-math .number').each(function(i, elem){
            var waypoint = new Waypoint({
                element: elem,
                offset: '100%',
                triggerOnce: true,
                handler		: function(){
                    var el			= $(elem);
                    var duration	= Math.floor((Math.random()*1000)+3000);
                    if(el.attr('data-duration')&&el.attr('data-duration')!=''){
                        duration = Math.floor(el.attr('data-duration'));
                    }
                    var to			= el.attr('data-to');
                    $({property:0}).animate({property:to}, {
                        duration	: duration,
                        easing		:'swing',
                        step		: function() {
                            el.text(Math.floor(this.property));
                        },
                        complete	: function() {
                            el.text(this.property);
                        }
                    });
                    this.destroy();
                }
            });
        });
    };

    /* ---------------------------------------------------------------------------
    * Chart
    * --------------------------------------------------------------------------- */
    window.init_chart = async function(el){
        if(!el){
            el = $('body');
        }
        el.find('.chart:not(.pix-loaded)').each(function(i, elem){
            $(elem).addClass('pix-loaded');
            var tbg = 'rgba(0,0,0,0.03)';
            if($(elem).attr('data-track')&&$(elem).attr('data-track')!=''){
                tbg =$(elem).attr('data-track');
            }
            var waypoint = new Waypoint({
                element:    elem,
                offset		: '100%',
                triggerOnce	: true,
                handler		: function(){
                    var color = $(this.element).attr('data-color');
                    $(this.element).easyPieChart({
                        animate		: 1000,
                        barColor: function(percent) {
                            var color = "";
                            if($(this.el).attr('data-gradient-1')){
                                var ctx = this.renderer.getCtx();
                                var canvas = this.renderer.getCanvas();
                                color = ctx.createRadialGradient(0,0,100, 100,70,70);
                                color.addColorStop(0, $(this.el).attr('data-gradient-1'));
                                if($(this.el).attr('data-gradient-3')&&$(this.el).attr('data-gradient-3')!=''){
                                    color.addColorStop(0.5, $(this.el).attr('data-gradient-2'));
                                    color.addColorStop(1, $(this.el).attr('data-gradient-3'));
                                }else{
                                    color.addColorStop(1, $(this.el).attr('data-gradient-2'));
                                }
                            }else{
                                color = $(this.el).attr('data-barColor');
                            }
                            return color;
                        },
                        trackColor: tbg,
                        lineCap		: 'round',
                        lineWidth	: 18,
                        size		: 140,
                        scaleColor	: false,
                        onStep: function(from, to, percent) {
                            $(this.el).find('.number').text(Math.round(percent));
                        }
                    });
                }
            });
        });
    }


    /* ---------------------------------------------------------------------------
    * Pix Sliders
    * --------------------------------------------------------------------------- */
    window.pix_sliders = function(){
        if( $('.pix-slider').length>0 || $('.pix-slider-nav-full').length>0 ){
            // pixLoadSlider(function(){
                $('.pix-slider').each(function(i, slider) {
                    var opts  = {};
                    if($(slider).attr('data-flickity')){
                        opts = JSON.parse($(slider).attr('data-flickity'));
                    }
                    opts.draggable= true;
                    opts.adaptiveHeight= true;
                    // opts.wrapAround= true;
                    opts.prevNextButtons= false;
                    opts.imagesLoaded= true;
                    opts.contain= true;
                    opts.resize= true;
                    opts.ready= function(){
                        // $('.pix-slider').flickity('resize');
                        setTimeout(function() {
                            $('.pix-slider').flickity('resize');
                        }, 900);
                    };
                    opts.on= {
                        ready: function() {
                            // $('.pix-slider').flickity('resize');
                            setTimeout(function() {
                                $('.pix-slider').flickity('resize');
                            }, 900);
                        }
                    };
                    $(slider).flickity(opts);
                });
                setTimeout(function(){
                    $('.pix-slider-nav-full').each(function(i, nav) {
                        var slider = false;
                        var align = 'center';
                        if($(nav).attr('data-slider')){
                            if($(nav).attr('data-nav-align')){
                                align = $(nav).attr('data-nav-align');
                            }
                            slider = $(nav).attr('data-slider');
                            $(nav).flickity({
                                asNavFor: slider,
                                cellAlign: align,
                                prevNextButtons: false,
                                contain: true,
                                pageDots: false,
                                on: {
                                    ready: function() {
                                        // $(nav).flickity('resize');
                                        setTimeout(function() {
                                            $(nav).flickity('resize');
                                        }, 1200);
                                    }
                                }
                            });
                        }

                    });
                }, 500);
            // });
        }

    }

    pix_sliders();

    window.pix_main_slider =  function(el){
        // window.pix_init_slider(el);
        if(!el){
            el = $('body');
        }
        if(el.find('.pix-main-slider').length>0){
            // pixLoadSlider(function(){
                var $sliders = el.find('.pix-main-slider');
                $sliders.each(function(i, elem){

                    if($(elem).hasClass('flickity-enabled')){
                        $(elem).flickity('destroy');
                    }
                    var opts  = {};
                    if($(elem).attr('data-flickity')){
                        opts = JSON.parse($(elem).attr('data-flickity'));
                    }
                    opts.draggable = true;
                    if(opts.adaptiveHeight) opts.adaptiveHeight = true;
                    opts.resize = true;
                    opts.imagesLoaded = true;
                    opts.arrowShape = 'M83.7718595,45.4606514 L31.388145,45.4606514 L54.2737785,23.1973134 C56.1027533,21.4180712 56.1027533,18.4982892 54.2737785,16.719047 C52.4448037,14.9398048 49.4903059,14.9398048 47.6613311,16.719047 L16.7563465,46.7836776 C14.9273717,48.5629198 14.9273717,51.4370802 16.7563465,53.2163224 L47.6613311,83.280953 C49.4903059,85.0601952 52.4448037,85.0601952 54.2737785,83.280953 C56.1027533,81.5017108 56.1027533,78.6275504 54.2737785,76.8483082 L31.388145,54.5849702 L83.7718595,54.5849702 C86.3511829,54.5849702 88.4615385,52.5319985 88.4615385,50.0228108 C88.4615385,47.5136231 86.3511829,45.4606514 83.7718595,45.4606514 Z';
                    // if($( window ).width()<600) opts.autoPlay = false;
                    $(elem).on( 'ready.flickity', function() {
                        if(opts.pix_id && $(opts.pix_id).hasClass('flickity-enabled') ){
                            setTimeout(function(){ $(opts.pix_id).flickity('resize'); }, 500);
                        }
                        if(opts.pix_id && $(opts.pix_id).hasClass('flickity-enabled') ){
                            setTimeout(function(){ $(opts.pix_id).flickity('resize'); }, 1500);
                        }
                        setTimeout(function(){
                            $(elem).addClass('pix-slider-loaded');
                        },100);

                    });
                    $(elem).flickity((opts));
                    if(opts.slider_effect){
                        var slider_style = '';
                        if(opts.slider_style) slider_style = opts.slider_style;
                        $(elem).closest('.vc_row:not(.overflow-visible)').removeClass('vc_row_visible').addClass('overflow-hidden').css({'overflow': 'hidden !important'});
                        $(elem).closest('.elementor-top-section').addClass('overflow-hidden').css({'overflow': 'hidden !important'});
                        var frameRender = 4;
                        $(elem).on( 'scroll.flickity', function( event, progress ) {
                            var el_width = $(elem).width();
                            if($( window ).width()<600) return false;
                            var el_left = $(elem).offset().left;
                            var slideWidth = $(elem).find('.carousel-cell').width();
                            if(!$(elem).data('flickity') || !$(elem).data('flickity').slides) return false;
                            $(elem).data('flickity').slides.forEach(function(slide, j) {
                                var flkInstanceSlide = $(elem).find('.carousel-cell:nth-child(' + (j + 1) + ')');
                                var slide_offset = $(slide.cells[0].element).offset().left;
                                var op = 1;
                                var local_offset = 0;
                                var rotate = 0;
                                var translate = 0;
                                var scale = 1;
                                var depth = 0;
                                var index = 10;
                                var pointer = 'auto';
                                if(slide_offset - el_left < 0 ){
                                        if(opts.slider_effect== 'pix-circular-slider'
                                        || opts.slider_effect== 'pix-circular-left'
                                        || opts.slider_effect== 'pix-fade-out-effect'
                                    ){
                                        local_offset = slide_offset - el_left;
                                        op = 1 + ( local_offset / slideWidth);
                                        if(op<0){op=0;}
                                        if(op>1){op=1;}
                                        if(opts.slider_effect!='pix-fade-out-effect'){
                                            rotate = (1-op)*20;
                                            translate =  1.8 * ( -1 * slide_offset + el_left );
                                            depth = -180 * ( (el_left-slide_offset) / slideWidth);
                                            scale = 1- ((1 - op)*0.1);
                                        }
                                    }else if(slider_style=='pix-opacity-slider'){
                                        local_offset = slide_offset - el_left;
                                        op = 1 + ( local_offset / slideWidth);
                                        if(op<0.3){op=0.3;}
                                        if(op>1){op=1;}
                                    }
                                    if(op<0.1) op = 0;
                                    if( (slide_offset - el_left) < -10 ){
                                        pointer = 'none';
                                    }


                                    index = -1;
                                }else if(slide_offset  > (el_left + el_width - slideWidth + 1) ){
                                    pointer = 'none';
                                    if(opts.slider_effect== 'pix-circular-slider'
                                        || opts.slider_effect== 'pix-circular-right'
                                        || opts.slider_effect== 'pix-fade-out-effect'
                                    ){
                                        local_offset = el_left  + el_width - slide_offset;
                                        op =  local_offset / slideWidth;
                                        if(op<0){op=0;}
                                        if(op>1){op=1;}
                                        if(opts.slider_effect!='pix-fade-out-effect'){
                                            rotate = -1 * (1-op)*20;
                                            translate = -1*(1-op)*2.2*slideWidth * 0.82;
                                            depth = -1*(1-op)*slideWidth*0.52;
                                            scale = 1- ((1 - op)*0.1);
                                        }
                                    }else if(slider_style=='pix-opacity-slider'){
                                        local_offset = el_left  + el_width - slide_offset;
                                        op =  local_offset / slideWidth;
                                        if(op<0.3){op=0.3;}
                                        if(op>1){op=1;}
                                    }
                                    index = -1;
                                    if(op<0.2) op = 0;
                                }
                                flkInstanceSlide.find('.slide-inner').css({
                                    'transform': 'perspective('+slideWidth+'px) translateX(' + translate + 'px) rotateY(' + rotate + 'deg) translateZ( '+depth+'px)',
                                    '-webkit-transform': 'perspective('+slideWidth+'px) translateX(' + translate + 'px) rotateY(' + rotate + 'deg) translateZ( '+depth+'px)',
                                    '-moz-transform': 'perspective('+slideWidth+'px) translateX(' + translate + 'px) rotateY(' + rotate + 'deg) translateZ( '+depth+'px)'
                                });
                                if(opts.slider_effect== 'pix-circular-slider'
                                || opts.slider_effect== 'pix-circular-right'
                                || opts.slider_effect== 'pix-circular-left'
                                || opts.slider_effect== 'pix-fade-out-effect'
                                ){
                                    flkInstanceSlide.css({
                                        'opacity': op,
                                        'z-index': index
                                    });
                                }
                                flkInstanceSlide.css({
                                    'pointer-events': pointer
                                });
                                flkInstanceSlide.parent().css({
                                    'pointer-events': pointer
                                });
                            });
                        });
                    }
                    document.addEventListener('scroll', (e) => {
                        let rect = elem.getBoundingClientRect();
                        let rect_bottom = rect.top + $(elem).height();
                        if(rect.top <= $(window).height() && rect_bottom >= -1){
                            $(elem).flickity('unpausePlayer');
                        }else{
                            $(elem).flickity('pausePlayer');
                        }
                    }, {
                        passive: true
                    });
                });
            // });
        }
}
window.pix_main_slider();

/* ---------------------------------------------------------------------------
* Pix Countdown
* --------------------------------------------------------------------------- */
    window.pix_countdown = async function(el){
        if(!el){
            el = $('body');
        }
        if(el.find('.pix-countdown:not(.pix-count-loaded)').length){
            el.find('.pix-countdown:not(.pix-count-loaded)').each(function(i, elem){
                var endDate = $(elem).attr('data-date');
                $(elem).countdown({
                    date: endDate,
                    render: function(data) {
                        $.each(data, function(key, value) {
                            $(elem).find('.pix-count-'+key).html(value);
                        });
                    },
                    onEnd: function(){
                        if($(this.el).attr('data-redirect')){
                            if(!$('body').hasClass('compose-mode')&&!$('body').hasClass('elementor-editor-active')){
                                window.location.href = $(this.el).attr('data-redirect');
                            }

                        }
                    }
                });
                $(elem).addClass('pix-count-loaded');
            });
        }
    }

  window.pix_animation_display = async function(el=false){
    if(!el){
        el = $('body');
    }
    var effects	=	[
        'fade-in-Img',
        'fade-in-down',
        'fade-in-left',
        'fade-in-up',
        'fade-in-up-big',
        'fade-in-right-big',
        'fade-in-left-big',
        'highlight-grow',
        'slide-in-up'
    ];
    el.find('.animate-in').each(function(i, elem){
        var	type = $(elem).attr('data-anim-type'),
        delay = $(elem).attr('data-anim-delay');
        $(elem).addClass('pix-waiting');
        // Animate
        if($(elem).hasClass('animate-in') && !$(elem).hasClass('animating-init')){
            $(elem).addClass('animating-init');
            setTimeout(function() {
                $(elem).addClass('animating').addClass(type).removeClass('animate-in');
            }, delay);

            // On animation end
            $(elem).one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend transitionend webkitTransitionEnd oTransitionEnd', function() {
                // Clear animation
                $(elem).removeClass('animating animating-init').removeClass(effects.join(' ')).addClass('animated');
            });
        }
    });
}
window.pix_animation = async function(el=false, refresh=false){
    var effects	=	[
        'fade-in-Img',
        'fade-in-down',
        'fade-in-left',
        'fade-in-up',
        'fade-in-up-big',
        'fade-in-right-big',
        'fade-in-left-big',
        'highlight-grow',
        'slide-in-up'
    ];
    if(!el){
        el = $('body');
    }
    var state = ':not(.pix-waiting)';
    if(refresh){
        state = '';
    }
    el.find('.animate-in'+state).each(function(i, elem){
        var normal_trigger = true;
        var offset = '100%';
        if($('body').hasClass('pix-sections-stack') && !$('body').hasClass('vc_editor')){
            if( window.innerWidth > mobileBreakPoint ){
                if($(elem).closest('section').length>0){
                    normal_trigger = false;
                    var offset = '200%';
                    if(!$(elem).closest('section').hasClass('is-sticky-active') && $(elem).closest('.site-footer2').length<1){
                        return false;
                    }
                }
            }
        }
        var	type = $(elem).attr('data-anim-type'),
        delay = $(elem).attr('data-anim-delay');
        $(elem).addClass('pix-waiting');
        var waypoint = new Waypoint({
            element: elem,
            offset: offset,
            triggerOnce: normal_trigger,
            handler: function() {
                // Animate
                if($(elem).hasClass('animate-in') && !$(elem).hasClass('animating-init')){
                    $(elem).addClass('animating-init');
                    setTimeout(function() {
                        $(elem).addClass('animating').addClass(type).removeClass('animate-in');
                    }, delay);

                    // On animation end
                    $(elem).one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend transitionend webkitTransitionEnd oTransitionEnd', function() {
                        // Clear animation
                        $(elem).removeClass('animating animating-init').removeClass(effects.join(' ')).addClass('animated');
                    });
                }
                // trigger Once
                this.destroy();
            }
        });
    });
}
window.isInViewport = function (elem) {
    var bounding = elem.getBoundingClientRect();
    return (
        bounding.top >= -10 &&
        bounding.bottom <= (window.innerHeight || document.documentElement.clientHeight) + 10
    );
};

})(jQuery);
