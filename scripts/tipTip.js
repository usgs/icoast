 /*
 * TipTip
 * Copyright 2010 Drew Wilson
 * www.drewwilson.com
 * code.drewwilson.com/entry/tiptip-jquery-plugin
 *
 * Version 1.3   -   Updated: Mar. 23, 2010
 *
 * This Plug-In will create a custom tooltip to replace the default
 * browser tooltip. It is extremely lightweight and very smart in
 * that it detects the edges of the browser window and will make sure
 * the tooltip stays within the current window size. As a result the
 * tooltip will adjust itself to be displayed above, below, to the left
 * or to the right depending on what is necessary to stay within the
 * browser window. It is completely customizable as well via CSS.
 *
 * This TipTip jQuery plug-in is dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 */

(function($){
	$.fn.tipTip = function(options) {
		var defaults = {
			activation: "hover",
			keepAlive: false,
			maxWidth: "250px",
			edgeOffset: 3,
			defaultPosition: "right",
			delay: 1000,
			fadeIn: 200,
			fadeOut: 200,
			attribute: "title",
			content: false, // HTML or String to fill TipTIp with
		  	enter: function(){},
		  	exit: function(){}
	  	};
	 	var opts = $.extend(defaults, options);

	 	// Setup tip tip elements and render them to the DOM
	 	if($("#tiptip_holder").length <= 0){
	 		var tiptip_holder = $('<div id="tiptip_holder"></div>');
			var tiptip_content = $('<div id="tiptip_content"></div>');
			var tiptip_arrow = $('<div id="tiptip_arrow"></div>');
			$("body").append(tiptip_holder.html(tiptip_content).prepend(tiptip_arrow.html('<div id="tiptip_arrow_inner"></div>')));
		} else {
			var tiptip_holder = $("#tiptip_holder");
			var tiptip_content = $("#tiptip_content");
			var tiptip_arrow = $("#tiptip_arrow");
		}

		return this.each(function(){
			var org_elem = $(this);
			if(opts.content){
				var org_title = opts.content;
			} else {
                var org_title = org_elem.attr(opts.attribute);
			}
			if(typeof org_title !== "undefined" && org_title != ""){
				if(!opts.content){
					org_elem.removeAttr(opts.attribute); //remove original Attribute
				}
				var timeout = false;

				if(opts.activation == "hover"){
					org_elem.hover(function(){
						active_tiptip();
					}, function(){
						if(!opts.keepAlive){
							deactive_tiptip();
						}
					});
					if(opts.keepAlive){
						tiptip_holder.hover(function(){}, function(){
							deactive_tiptip();
						});
					}
				} else if(opts.activation == "focus"){
					org_elem.focus(function(){
						active_tiptip();
					}).blur(function(){
						deactive_tiptip();
					});
				} else if(opts.activation == "click"){
					org_elem.click(function(){
						active_tiptip();
						return false;
					}).hover(function(){},function(){
						if(!opts.keepAlive){
							deactive_tiptip();
						}
					});
					if(opts.keepAlive){
						tiptip_holder.hover(function(){}, function(){
							deactive_tiptip();
						});
					}
				}

				function active_tiptip(){
                    //    console.log('-------------------------------------------------------------------------');
					opts.enter.call(this);
					tiptip_content.html(org_title);
                    //    console.log(org_title);
					tiptip_holder.hide().removeAttr("class").css({
                        "margin":"0",
                        "max-width": opts.maxWidth
                    });
					tiptip_arrow.removeAttr("style");
                    //    console.log();
					var top = parseInt(org_elem.offset()['top']);
                    //    console.log('top: ' + top);
					var left = parseInt(org_elem.offset()['left']);
                    //    console.log('left: ' + left);
					var org_width = parseInt(org_elem.outerWidth());
                    //    console.log('org_width: ' + org_width);
					var org_height = parseInt(org_elem.outerHeight());
                    //    console.log('org_height: ' + org_height);
					var tip_w = tiptip_holder.outerWidth();
                    //    console.log('tip_w: ' + tip_w);

                    tiptip_holder.show();
                    var img_h = 0;
                    var p_h = 0;
                    if ($('#tiptip_content img').length) {
                        img_h = $('#tiptip_content img').attr('height');
                        //    console.log('img_h: ' + img_h );
                    }
                    if ($('#tiptip_content p').length) {
                        p_h = $('#tiptip_content p').outerHeight(true);
                        //    console.log('p_h: ' + p_h );
                    }
                    //    console.log(tiptip_holder.outerWidth(true));
                    //    console.log(tiptip_content.width());
                    if (img_h != 0 || p_h != 0) {
                        var tip_vertical_spacing = tiptip_holder.outerWidth(true) - tiptip_content.width();
                        //    console.log('tip_vertical_spacing: ' + tip_vertical_spacing );

                        var tip_h = parseInt(img_h) + p_h + tip_vertical_spacing;
                        //    console.log('tip_h: ' + tip_h );
                    } else {
                        var tip_h = tiptip_holder.outerHeight();
                    }
                    tiptip_holder.hide();
                    //    console.log('Window Width: ' + parseInt($(window).width()));



					var w_compare = Math.round((org_width - tip_w) / 2);
                    //    console.log('w_compare: ' + w_compare);
					var h_compare = Math.round((org_height - tip_h) / 2);
                    //    console.log('h_compare: ' + h_compare);
					var marg_left = Math.round(left + w_compare);
                    //    console.log('marg_left: ' + marg_left);
					var marg_top = Math.round(top + org_height + opts.edgeOffset);
                    //    console.log('marg_top: ' + marg_top);
					var t_class = "";
					var arrow_top = "";
					var arrow_left = Math.round(tip_w - 12) / 2;

                    if(opts.defaultPosition == "bottom"){
                    	t_class = "_bottom";
                   	} else if(opts.defaultPosition == "top"){
                   		t_class = "_top";
                   	} else if(opts.defaultPosition == "left"){
                   		t_class = "_left";
                   	} else if(opts.defaultPosition == "right"){
                   		t_class = "_right";
                   	}

                    // left_off_screen = boolean Will left of tip be off screen to the left if centered
                    // over element and screen scrolled to the right
					var left_off_screen = (left - tip_w) < parseInt($(window).scrollLeft());
                    //    console.log('left_off_screen: ' + left_off_screen);
                    // right_off_screen = boolean Will left of tip be off screen to the right if left side of
                    // tip and element are aligned.
					var right_off_screen = (tip_w + left + org_width + 5) > (parseInt($(window).width()) + parseInt($(window).scrollLeft()));
                    //    console.log('right_off_screen: ' +right_off_screen);
					if((left_off_screen) || (t_class == "_right" && !right_off_screen) || (t_class == "_left" && left < (tip_w + opts.edgeOffset + 5))){
						t_class = "_right";
						arrow_top = Math.round(tip_h - 13) / 2;
						arrow_left = -12;
						marg_left = Math.round(left + org_width + opts.edgeOffset);
						marg_top = Math.round(top + h_compare);
					} else if((right_off_screen) || (t_class == "_left" && !left_off_screen)){
						t_class = "_left";
						arrow_top = Math.round(tip_h - 13) / 2;
						arrow_left =  Math.round(tip_w);
						marg_left = Math.round(left - (tip_w + opts.edgeOffset + 5));
						marg_top = Math.round(top + h_compare);
					}

                    // bottom_off_screen = boolean True If tip is below element will it go off the bottom of the page
					var bottom_off_screen = (top + org_height + opts.edgeOffset + tip_h + 8) > parseInt($(window).height() + $(window).scrollTop());
					// top_off_screen = boolean True if tip be off the top of the screen if bottoms of tip and
                    // element are aligned.
                    var top_off_screen = (top - (opts.edgeOffset + tip_h + 8)) < 0;



					if(bottom_off_screen || (t_class == "_bottom" && bottom_off_screen) || (t_class == "_top" && !top_off_screen)){
						if(t_class == "_top" || t_class == "_bottom"){
							t_class = "_top";
						} else {
							t_class = t_class+"_top";
						}
						arrow_top = tip_h;
						marg_top = Math.round(top - (tip_h + 5 + opts.edgeOffset));
					} else if(top_off_screen | (t_class == "_top" && top_off_screen) || (t_class == "_bottom" && !bottom_off_screen)){
						if(t_class == "_top" || t_class == "_bottom"){
							t_class = "_bottom";
						} else {
							t_class = t_class+"_bottom";
						}
						arrow_top = -12;
						marg_top = Math.round(top + org_height + opts.edgeOffset);
					}

					if(t_class == "_right_top" || t_class == "_left_top"){
						marg_top = marg_top + 5;
					} else if(t_class == "_right_bottom" || t_class == "_left_bottom"){
						marg_top = marg_top - 5;
					}
					if(t_class == "_left_top" || t_class == "_left_bottom"){
						marg_left = marg_left + 5;
					}
					tiptip_arrow.css({"margin-left": arrow_left+"px", "margin-top": arrow_top+"px"});
					tiptip_holder.css({
                        "margin-left": marg_left+"px",
                        "margin-top": marg_top+"px"
                    }).attr("class","tip"+t_class);

					if (timeout){ clearTimeout(timeout); }
					timeout = setTimeout(function(){ tiptip_holder.stop(true,true).fadeIn(opts.fadeIn); }, opts.delay);
				}

				function deactive_tiptip(){
					opts.exit.call(this);
					if (timeout){ clearTimeout(timeout); }
					tiptip_holder.fadeOut(opts.fadeOut);
				}
			}
		});
	}
})(jQuery);