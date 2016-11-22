/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
var layout;
var lockPosition;
var hideControls;
var lowDesignerScale;
var currentSelectedIdx = 0;
function updateRegionInfoBase(element) 
{
    var pos = $(element).position();

    var scale = ($(element).closest('.layout').attr("version") == 1) ? (1 / $(element).attr("tip_scale")) : $(element).attr("designer_scale");
    $('.region-tip', element).html(Math.round($(element).width() / scale, 0) + 
                                " x " + 
                                Math.round($(element).height() / scale, 0) + 
                                " (" + 
                                    Math.round(pos.left / scale, 0) + 
                                    "," + 
                                    Math.round(pos.top / scale, 0) + ") ");
}
function regionPositionUpdateBase(element, refreshPreview)
 {
    var curDesignerScale = parseFloat($(element).attr("designer_scale"));
    var width   = parseFloat($(element).css("width"));
    var height  = parseFloat($(element).css("height"));
    var left   = parseFloat($(element).css("left"));
    var top  = parseFloat($(element).css("top"));
    var pos = $(element).position();
    var regionid = $(element).attr("regionid");

    // Update the region width / height attributes
    $(element).attr("width", $(element).width() / curDesignerScale).attr("element", $(element).height() / curDesignerScale).attr("left", pos.left / curDesignerScale).attr("top", pos.top / curDesignerScale);

    // Update the Preview for the new sizing
    if (refreshPreview)
    {
        console.log("refresh preview");
        var preview = Preview.instances[regionid];
        preview.SetSequence(preview.seq);
    }
    // Expose a new button to save the positions
    $("#layout-save-all").removeClass("disabled");
    $("#layout-revert").removeClass("disabled");
}
toggleRegionSelectedIcon = function(regionitem)
{
    //console.log(regionitem);
    if ($(regionitem).hasClass('ui-selected') && $(regionitem).hasClass('regionSelected') == false)
    {
        $(regionitem).removeClass("ui-selected");
        $(regionitem).find('.regionitemselectedicon').addClass('regionselectediconOff');
        $(regionitem).attr('selectedindex', -1);
    }
    else
    {
        $(regionitem).addClass("ui-selected");
        $(regionitem).find('.regionitemselectedicon').removeClass('regionselectediconOff');
        $(regionitem).attr('selectedindex', currentSelectedIdx++);
    }
    if ($(regionitem).hasClass('regionSelected'))
    {
        $(regionitem).addClass("ui-selected");
    } 
};
DeSelecteAllRegion = function() {
    $('.regionitem.ui-selected').each(function()
    {
        toggleRegionSelectedIcon($(this));
    });
};
AdjustSelectedRegionPosition = function(postype)
{
    //console.log('adjustpos: type=' + postype);
    var canvasItem = $(".layout");

    var anchoritem = undefined;
    var minselectedIdx = 99999999;
    var totalitems = 0;
    $('.regionitem.ui-selected').each(function()
    {
        /*
        var thisselectedidx = parseInt($(this).attr('selectedindex'));
        if (thisselectedidx < minselectedIdx)
        {
            minselectedIdx = thisselectedidx;
            anchoritem = $(this);
        }
        */
        if ($(this).hasClass('regionSelected'))
            anchoritem = $(this);
        totalitems++;
    });
    console.log('totalitems: ' + totalitems);
    if (anchoritem != undefined && postype < 10 && totalitems > 1)
    {
        if (postype == 0)   // align top
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('top', $(anchoritem).css('top'));
            });
        }
        if (postype == 1)   // align bottom
        {
            $('.regionitem.ui-selected').each(function()
            {
                console.log(parseFloat($(anchoritem).css('top'))+ $(anchoritem).height() - $(this).height());
                $(this).css('top', parseFloat($(anchoritem).css('top')) + $(anchoritem).height() - $(this).height());
            });
        }        
        if (postype == 2)   // align left
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('left', $(anchoritem).css('left'));
            });
        }        
        if (postype == 3)   // align right
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('left', parseFloat($(anchoritem).css('left'))+$(anchoritem).width()-$(this).width());
            });
        }   
        if (postype == 4)   // align v-center
        {
            var v_center = parseFloat($(anchoritem).css('top')) + $(anchoritem).height() / 2;
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('top', v_center - $(this).height() / 2);
            });
        }         
        if (postype == 5)   // align h-center
        {
            var h_center = parseFloat($(anchoritem).css('left')) + $(anchoritem).width() / 2;
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('left', h_center - $(this).width() / 2);
            });
        }
        $('.regionitem.ui-selected').each(function()
        {           
            regionPositionUpdateBase($(this), false);
            updateRegionInfoBase($(this));
        });            
    }
    if (anchoritem != undefined && (postype > 10 && postype < 20) && totalitems > 1)
    {
        if (postype == 11)   // V-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('width', $(anchoritem).css('width'));
            });
        }
        if (postype == 12)   // H-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('height', $(anchoritem).css('height'));
            });
        }  
        if (postype == 13)   // H&V-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('height', $(anchoritem).css('height'));
                $(this).css('width', $(anchoritem).css('width'));
            });
        }              
        $('.regionitem.ui-selected').each(function()
        {           
            regionPositionUpdateBase($(this), true);
            updateRegionInfoBase($(this));
        });        
    }

    if (anchoritem != undefined && (postype > 20 && postype < 30) && totalitems > 0)
    {
        var canvasW = parseFloat(canvasItem.css("width"));
        var canvasH = parseFloat(canvasItem.css("height"));

        if (postype == 21)   // Canvas V-center
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('top', (canvasH - parseFloat($(this).css('height'))) / 2);
            });
        }
        if (postype == 22)   // Canvas H center
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('left', (canvasW - parseFloat($(this).css('width'))) / 2);
            });
        }  
        if (postype == 23)   // Canvas Center
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('top', (canvasH - parseFloat($(this).css('height'))) / 2);
                $(this).css('left', (canvasW - parseFloat($(this).css('width'))) / 2);
            });
        }    

        if (postype == 24)   // Canvas V-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('width', canvasW);
            });
        }
        if (postype == 25)   // Canvas H-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('height', canvasH);
            });
        }  
        if (postype == 26)   // Canvas H&V-size
        {
            $('.regionitem.ui-selected').each(function()
            {
                $(this).css('height', canvasH);
                $(this).css('width', canvasW);
            });
        }                   
        $('.regionitem.ui-selected').each(function()
        {           
            regionPositionUpdateBase($(this), true);
            updateRegionInfoBase($(this));
        });        
    }    
};
$(document).ready(function(){
    $('.regionitem').each(function()
    {
        updateRegionInfoBase($(this));
    });
    // Set the height of the grid to be something sensible for the current screen resolution
    var jumpList = $("#layoutJumpList");

    jumpList.selectpicker();

    jumpList.on("changed.bs.select", function(event, index, newValue, oldValue) {
        localStorage.liveSearchPlaceholder = $(this).parent().find(".bs-searchbox input").val();
        window.location = $(this).val();
    }).on("shown.bs.select", function() {
        $(this).parent().find(".bs-searchbox input").val(localStorage.liveSearchPlaceholder);
        $(this).selectpicker("refresh");

        // Shrink the Dropdown list according to the container (HAX)
        var jumpListContainer = $(".layoutJumpListContainer");
        jumpListContainer.find(".bootstrap-select").width(jumpListContainer.width());
    });

    // Shrink the Dropdown list according to the container (HAX)
    var jumpListContainer = $(".layoutJumpListContainer");
    jumpListContainer.find(".bootstrap-select").width(jumpListContainer.width());

    layout = $("#layout");

    // Read in the values of lockPosition and hideControls
    lockPosition = $("input[name=lockPosition]")[0].checked;
    hideControls = $("input[name=hideControls]")[0].checked;
    lowDesignerScale = (layout.attr("designer_scale") < 0.41);

    if (lowDesignerScale)
        $("input[name=lockPosition]").attr("disabled", true);

    // Hover functions for previews/info
    configureRegionHandler();

    
    // Initial Drag and Drop configuration
    configureDragAndDrop();

    // Preview
    $('.regionPreview', layout).each(function()
    {
        //console.log("New Preview for " + $(this).attr('regionid'));
        new Preview(this);
    });

    // Set an interval
    if ($("#layout-status").length > 0) 
    {
        layoutStatus(layout.data('statusUrl'));
        setInterval("layoutStatus('" + layout.data('statusUrl') + "')", 1000 * 60); // Every minute
    }

    // Bind to the switches
    $(".switch-check-box").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) 
    {
        var propertyName = $(this).prop("name");

        if (propertyName == "lockPosition") {
            lockPosition = state;
            configureDragAndDrop();
        }
        else if (propertyName == "hideControls") {
            hideControls = state;

            if (hideControls) {
                $(".region .regionInfo").hide();
                $(".region .previewNav").hide();
            } else {
                $(".region .regionInfo").show();
                $(".region .previewNav").show();
            }
        }

        // Update the user preference
        updateUserPref([{
            option: propertyName,
            value: state
        }]);

    });

    // Hide region previews/info
    setTimeout(function() {
        $(".region .regionInfo").hide("200");
        $(".region .previewNav").hide("200");
    }, 500);

    // Bind to the region options menu
    $('.RegionOptionsMenuItem').off('click');
    $('.RegionOptionsMenuItem').on('click', function(e) 
    {
        e.preventDefault();

        // If any regions have been moved, then save them.
        if (!$("#layout-save-all").hasClass("disabled")) {
            SystemMessage(translation.savePositionsFirst, true);
            return;
        }

        var data = {
            layoutid: $(this).closest('.region').attr("layoutid"),
            regionid: $(this).closest('.region').attr("regionid"),
            scale: $(this).closest('.region').attr("tip_scale"),
            zoom: $(this).closest('.layout').attr("zoom")
        };

        var url = $(this).prop("href");
        XiboFormRender($(this), data);
    });

    // Bind to the save/revert buttons
    $("#layout-save-all").off("click");
    $("#layout-save-all").on("click", function () 
    {
        // Save positions for all layouts / regions
        savePositions();
        return false;
    });
    $("#layout-revert").off("click");
    $("#layout-revert").on("click", function () 
    {
        // Reload
        location.reload();
        return false;
    });

    // Bind to the save size button
    $("#saveDesignerSize").off("click");
    $("#saveDesignerSize").on("click", function () {
        // Update the user preference
        updateUserPref([{
            option: "defaultDesignerZoom",
            value: $(this).data().designerSize
        }]);each
    });

    // Bind to the save size button
    $("#layoutzoomout").off("click");
    $("#layoutzoomout").on("click", function () 
    {
        layout = $("#layout");
        var oldDesignerScale = parseFloat(layout.attr("designer_scale"));
        curDesignerScale = oldDesignerScale - 0.3;

        if (curDesignerScale != oldDesignerScale)
        {
            var layoutWidth = parseFloat(layout.attr('width'));
            var layoutHeight = parseFloat(layout.attr('height'));

            layout.attr("designer_scale", curDesignerScale);
            layout.css('width', layoutWidth * curDesignerScale);
            layout.css('height', layoutHeight * curDesignerScale);

            leachayout.find(".region").each(function()
            {
                var regionWidth = parseFloat($(this).attr("width"));
                var regionHeight = parseFloat($(this).attr("height"));
                var regionTop = parseFloat($(this).attr("top"));
                var regionLeft = parseFloat($(this).attr("left"));
                var regionId = $(this).attr("regionId");

                // Update the region width / height attributes
                $(this).css("width", regionWidth * curDesignerScale).
                        css("height", regionHeight * curDesignerScale).
                        css("left", regionLeft * curDesignerScale).
                        css("top", regionTop * curDesignerScale).
                        attr('designer_scale', curDesignerScale);

                // refresh preview
                refreshPreview(regionId);

                
            });
            lowDesignerScale = (curDesignerScale < 0.41);
            if (lowDesignerScale)
                $("input[name=lockPosition]").attr("disabled", true);
            else
                $("input[name=lockPosition]").attr("disabled", false);                        
        }
    });
    $("#layoutzoomin").off("click");
    $("#layoutzoomin").on("click", function () 
    {
        layout = $("#layout");
        var oldDesignerScale = parseFloat(layout.attr("designer_scale"));
        curDesignerScale = oldDesignerScale + 0.3;


        if (curDesignerScale != oldDesignerScale)
        {
            var layoutWidth = parseFloat(layout.attr('width'));
            var layoutHeight = parseFloat(layout.attr('height'));

            layout.attr("designer_scale", curDesignerScale);
            layout.css('width', layoutWidth * curDesignerScale);
            layout.css('height', layoutHeight * curDesignerScale);

            layout.find(".region").each(function()
            {
                var regionWidth = parseFloat($(this).attr("width"));
                var regionHeight = parseFloat($(this).attr("height"));
                var regionTop = parseFloat($(this).attr("top"));
                var regionLeft = parseFloat($(this).attr("left"));
                var regionId = $(this).attr("regionId");

                // Update the region width / height attributes
                $(this).css("width", regionWidth * curDesignerScale).
                        css("height", regionHeight * curDesignerScale).
                        css("left", regionLeft * curDesignerScale).
                        css("top", regionTop * curDesignerScale).
                        attr('designer_scale', curDesignerScale);

                // refresh preview
                refreshPreview(regionId);               
            });
            lowDesignerScale = (curDesignerScale < 0.41);
            if (lowDesignerScale)
                $("input[name=lockPosition]").attr("disabled", true);
            else
                $("input[name=lockPosition]").attr("disabled", false);                        
        }
    });
    // Hook up toggle
    $('[data-toggle="tooltip"]').tooltip();

    /* vertical media list, draggable */
    configureMedialistHandler();

    /* horizontal playlist, sortable, draggable, droppable */
    configurePlaylistHandler();

    $("#playlist-sortable-draggable").off("click");
    $("#playlist-sortable-draggable").on("click", '.playlistitemdeleteicon', function() {
        // find the nearest playlistitem to delete this item
        $(this).closest(".playlistitem").hide().remove();
        $('.playlistcarousel').jcarousel('reload');
        $('.playlistcarousel-pagination').jcarouselPagination('reloadCarouselItems');
    });       
});

function configureDragAndDrop() {

    // Do we want to bind?
    if (lockPosition || lowDesignerScale) {
        layout.find(".region").draggable("disable").resizable("disable");
    } else {
        layout.find(".region").draggable("enable").resizable("enable");
    }
}
function configureMedialistHandler()
{
    $( ".mediaitem" ).draggable({
        connectToSortable: "#playlist-sortable-draggable",
        appendTo: "body",
        helper: "clone",
        revert: "invalid",
        //cursor: "move",
        containment: ".wrapper",
        zIndex: 10000,
        scroll:false,
        start: function (event, ui) {
            $(this).hide();              
        },
        stop: function (event, ui) {
            $(this).show();
        }
    })
    .disableSelection()
    .hover(function()
    {
        $(this).find('.medialistitemsettingicon').removeClass('medialistitemsettingiconOff');
        //$(this).find('.medialistitemsettingicon').addClass('medialistitemsettingiconOn');
    },
    function() {
        $(this).find('.medialistitemsettingicon').addClass('medialistitemsettingiconOff');
        //$(this).find('.medialistitemsettingicon').removeClass('medialistitemsettingiconOn');        
    });
    $('.medialistitemsettingicon').off('click');
    $('.medialistitemsettingicon').on('click', function()
    {
        // try to launch playlist editing form
        var formurl = $(this).data().playlisteditformurl;
        formurl = formurl.replace(':id', $(this).data().playlistid);
        //console.log(formurl);
        XiboFormRender(formurl);
    });
}
function configurePlaylistHandler()
{
    $( "#playlist-sortable-draggable" ).sortable({
        connectWith: "#medialist-draggable",
        cursor: "move",
        revert: true,
        helper: "clone",
        appendTo: document.body,
        start: function(event, ui) {
            var start_pos = ui.item.index();
            ui.item.data('start_pos', start_pos);
        },
        change: function(event, ui) {
            var start_pos = ui.item.data('start_pos');
            var index = ui.placeholder.index();
            if (start_pos < index) {
                //$('#playlist-sortable-draggable li:nth-child(' + index + ')').addClass('highlights');
            } else {
                //$('#playlist-sortable-draggable li:eq(' + (index + 1) + ')').addClass('highlights');
            }
        },
        beforeStop: function (event, ui) { 
            $(ui.item).addClass('newInsertedMedia');
        },                        
        stop: function(event, ui) {
            $(ui.item).addClass('playlistitem').append('<i class="fa fa-cog fa-fw playlistitemdeleteicon" ></i><i class="fa fa-cog fa-fw playlistitemsettingicon"></i>');
            //$('.playlistcarousel').jcarousel('scroll', $(ui.item));
            $('.playlistcarousel').jcarousel('reload');
            $('.playlistcarousel-pagination').jcarouselPagination('reloadCarouselItems');
        }
    }).disableSelection();
}
function configureRegionHandler()
{
    //console.log('configureRegionHandler');
    var thisLayout = $("#layout");

    // Hover functions for previews/info
    thisLayout.find(".region")
        .hover(function() {
            if (!hideControls) 
            {
                thisLayout.find(".regionInfo").show();
                thisLayout.find(".previewNav").show();
            }
        }, function() {
            thisLayout.find(".regionInfo").hide();
            thisLayout.find(".previewNav").hide();
        })
        .draggable({
            containment: thisLayout,
            stop: regionPositionUpdateNoRefreshPreview,
            drag: function(event, ui) {        
                updateRegionInfo(event, ui);
            },
            start: function(event, ui) {
            },
            grid: [ 10, 10 ],
            snap: ".regionsnappableitem",
            snapTolerance: 10
        })
        .resizable({
            containment: thisLayout,
            minWidth: 25,
            minHeight: 25,
            stop: regionPositionUpdate,
            resize: updateRegionInfo
        })
        .selectable()
        .droppable({ 
            accept:'.mediaitem',
            tolerance:'pointer',
            greedy: true,               
            drop: function(event, ui) 
            {
                //console.log($(this).attr('id') + 'to be checked.');
                var topElement = undefined;
                //var topElementArray = Array();
                var zIndexHighest = 0;
                // Change it as you want to write in your code
                // For Container id or for your specific class for droppable or for both
                $('.mediadroppable').each(function()
                {                     
                    _left = $(this).offset().left, _right = $(this).offset().left + $(this).width();
                    _top = $(this).offset().top, _bottom = $(this).offset().top + $(this).height();
                    //console.log("item: " + $(this).attr('id') + "(" + _left + "," + _top + "," + _right + "," + _bottom + ")");
                    if (event.pageX >= _left && 
                        event.pageX <= _right && 
                        event.pageY >= _top && 
                        event.pageY <= _bottom) 
                    {   
                        //topElementArray.push($(this).attr('id'));
                        z_index = parseInt($(this).attr("zindex"));
                        //console.log($(this).attr('id') + ' ' + $(this).attr("zindex") + 'highest:' + zIndexHighest);
                        if (z_index > zIndexHighest)
                        {
                            zIndexHighest = z_index;
                            topElement = $(this);
                            //console.log('update higest' + z_index);;
                        }                    
                        
                    }
                });            
                    
                if ($(this).attr('id') == $(topElement).attr('id')) 
                {
                    //console.log('topelement' + ' ' + $(topElement).attr('id') + ' ' + $(topElement).attr("zindex"));
                    var thisregionid = $(this).attr('regionid');
                    //console.log($(this).attr('id') + "received");
                    //var thisP = $(this).find("p");
                    //thisP.html(thisP.html() + $(ui.draggable).attr("data-mediatype") + "[" + $(ui.draggable).attr('data-mediaid') + "]/");
                    // here, we would like to reset playlist
                    resetallplaylisturl = $(this).data().resetPlaylistUrl;
                    //console.log(resetallplaylisturl);
                    //console.log($(ui.draggable).data().playlistid);
                    resetallplaylisturl = resetallplaylisturl.replace(':playlistid', $(ui.draggable).data().playlistid);
                    //console.log(resetallplaylisturl);
                    $.ajax({
                        type: "put",
                        url: resetallplaylisturl,
                        cache: false,
                        dataType: "json"                        
                    })
                    .success(function(data) 
                    {
                        // 
                        XiboSubmitResponse(data);
                        //console.log(thisregionid);
                        refreshPreview(thisregionid);
                    });
                }                       
            }
        });

    // alex: region item
    /*
    $(".mediadroppable").off("click");
    $(".mediadroppable").on('click', function(e)
    {
        if ($(e.target).hasClass('regionPreviewOverlay') || 
            $(e.target).hasClass('previewContent') ||
            $(e.target).hasClass('preview') || 
            $(e.target).hasClass('regionTransparency')  || 
            $(e.target).hasClass('mediadroppable'))
        {       
            toggleRegionSelectedIcon($(this));     
        }   
    });
    */
    $('.RegionOptionsMenuItem').off('click');
    $('.RegionOptionsMenuItem').on('click', function(e) 
    {
        e.preventDefault();

        // If any regions have been moved, then save them.
        if (!$("#layout-save-all").hasClass("disabled")) {
            SystemMessage(translation.savePositionsFirst, true);
            return;
        }

        var data = {
            layoutid: $(this).closest('.region').attr("layoutid"),
            regionid: $(this).closest('.region').attr("regionid"),
            scale: $(this).closest('.region').attr("tip_scale"),
            zoom: $(this).closest('.layout').attr("zoom")
        };

        var url = $(this).prop("href");
        XiboFormRender($(this), data);
    });
    // Search for any Buttons / Links on the page that are used to load forms
    $(".XiboFormButton").off("click");
    $(".XiboFormButton").on("click", function() {

        XiboFormRender($(this));

        return false;
    });                      
}

/**
 * Update Region Information with Latest Width/Position
 * @param  {[type]} e  [description]
 * @param  {[type]} ui [description]
 * @return {[type]}    [description]
 */
/*
function updateRegionInfo(e, ui) 
{
    var pos = $(this).position();
    //console.log(regiondurationhtml);
    var scale = ($(this).closest('.layout').attr("version") == 1) ? (1 / $(this).attr("tip_scale")) : $(this).attr("designer_scale");
    $('.region-tip', this).html(Math.round($(this).width() / scale, 0) + 
                                " x " + 
                                Math.round($(this).height() / scale, 0) + 
                                " (" + 
                                    Math.round(pos.left / scale, 0) + 
                                    "," + 
                                    Math.round(pos.top / scale, 0) + ") ");
}
*/
function updateRegionInfo(e, ui) 
{
    var pos = $(ui.helper).position();
    //console.log(regiondurationhtml);
    var scale = ($(ui.helper).closest('.layout').attr("version") == 1) ? (1 / $(ui.helper).attr("tip_scale")) : $(ui.helper).attr("designer_scale");
    $('.region-tip', $(ui.helper)).html(Math.round($(ui.helper).width() / scale, 0) + 
                                " x " + 
                                Math.round($(ui.helper).height() / scale, 0) + 
                                " (" + 
                                    Math.round(pos.left / scale, 0) + 
                                    "," + 
                                    Math.round(pos.top / scale, 0) + ") ");
}

function regionPositionUpdate(e, ui) {
    var curDesignerScale = parseFloat($(this).attr("designer_scale"));
    var width   = parseFloat($(this).css("width"));
    var height  = parseFloat($(this).css("height"));
    var left   = parseFloat($(this).css("left"));
    var top  = parseFloat($(this).css("top"));
    var pos = $(this).position();
    var regionid = $(this).attr("regionid");

    // Update the region width / height attributes
    $(this).attr("width", $(this).width() / curDesignerScale).attr("height", $(this).height() / curDesignerScale).attr("left", pos.left / curDesignerScale).attr("top", pos.top / curDesignerScale);

    // Update the Preview for the new sizing
    var preview = Preview.instances[regionid];
    preview.SetSequence(preview.seq);

    // Expose a new button to save the positions
    $("#layout-save-all").removeClass("disabled");
    $("#layout-revert").removeClass("disabled");
}
function regionPositionUpdateNoRefreshPreview(e, ui) {
    var curDesignerScale = parseFloat($(this).attr("designer_scale"));
    var width   = parseFloat($(this).css("width"));
    var height  = parseFloat($(this).css("height"));
    var left   = parseFloat($(this).css("left"));
    var top  = parseFloat($(this).css("top"));
    var pos = $(this).position();
    var regionid = $(this).attr("regionid");

    // Update the region width / height attributes
    $(this).attr("width", $(this).width() / curDesignerScale).attr("height", $(this).height() / curDesignerScale).attr("left", pos.left / curDesignerScale).attr("top", pos.top / curDesignerScale);

    // Update the Preview for the new sizing
    //var preview = Preview.instances[regionid];
    //preview.SetSequence(preview.seq);

    // Expose a new button to save the positions
    $("#layout-save-all").removeClass("disabled");
    $("#layout-revert").removeClass("disabled");
}
function savePositions() {

    // Update all layouts
    layout.each(function(){

        $("#layout-save-all").addClass("disabled");
        $("#layout-revert").addClass("disabled");

        // Store the Layout ID
        var url = $(this).data().positionAllUrl;
        // Build an array of
        var regions = new Array();

        $(this).find(".region").each(function(){
            var designer_scale = $(this).attr("designer_scale");
            var position = $(this).position();
            var region = {
                width: $(this).width() / designer_scale,
                height: $(this).height() / designer_scale,
                top: position.top / designer_scale,
                left: position.left / designer_scale,
                regionid: $(this).attr("regionid")
            };

            // Update the region width / height attributes
            $(this).attr("width", region.width).attr("height", region.height);

            // Add to the array
            regions.push(region);
        });

        $.ajax({
                type: "put",
                url: url,
                cache: false,
                dataType: "json",
                data: {regions : JSON.stringify(regions) },
                success: XiboSubmitResponse
            });
    });
}

/**
 * Sets the layout to full screen
 */
function setFullScreenLayout(width, height) {
    $('#width', '.XiboForm').val(width);
    $('#height', '.XiboForm').val(height);
    $('#top', '.XiboForm').val('0');
    $('#left', '.XiboForm').val('0');
}

function refreshPreview(regionId) {
    // Refresh the preview
    var preview = Preview.instances[regionId];
    preview.SetSequence(preview.seq);

    // Clear the layout status
    $("#layout-status").removeClass("alert-success alert-danger").addClass("alert-info").html("<span class='fa fa-cog fa-spin'></span> " + translations.statusPending);
}

var loadTimeLineCallback = function(dialog) {

    dialog.addClass("modal-big");

    refreshPreview($('#timelineControl').attr('regionid'));

    $("li.timelineMediaListItem").hover(function() {

        var position = $(this).position();
        var scale = $('#layout').attr('designer_scale');

        // Change the hidden div's content
        $("div#timelinePreview")
            .html($("div.timelineMediaPreview", this).html())
            .css({
                "margin-top": /*position.top + */$('#timelineControl').closest('.modal-body').scrollTop()
            })
            .show();

        $("#timelinePreview .hoverPreview").css({
            width: $("div#timelinePreview").width() / scale,
            transform: "scale(" + scale + ")",
            "transform-origin": "0 0 ",
            background: $('#layout').css('background-color')
        })

    }, function() {
        return false;
    });

    $(".timelineSortableListOfMedia").sortable();

    // Hook up the library Upload Buttons
    $(".libraryUploadForm").click(libraryUploadClick);
};

function XiboPlaylistSaveAITags(formUrl) 
{
    bootbox.hideAll();
    XiboFormRender(formUrl);
};
// playlist save order from timeline view (layout editing page)
var XiboTimelineSaveOrder = function(timelineDiv) {

    var url = $("#" + timelineDiv).data().orderUrl;
    var i = 0;
    var widgets = {};

    $('#' + timelineDiv + ' li.timelineMediaListItem').each(function() {
        i++;
        widgets[$(this).attr("widgetid")] = i;
    });

    //console.log(widgets);


    // Call the server to do the reorder
    $.ajax({
        type:"post",
        url: url,
        cache:false,
        dataType:"json",
        data:{
            "widgets": widgets
        },
        success: [
            XiboSubmitResponse,
            afterDesignerSave
        ]
    });
};
// playlist save order from grid view (layout editing)
var XiboTimelineGridSaveOrder = function(timelinetable) {

    var url = $("#" + timelinetable).data().orderUrl;
    var i = 0;
    var widgets = {};

    $('#' + timelinetable + ' tr').each(function() 
    {
        i++;
        widgets[$(this).attr("widgetid")] = i;
    });

    //console.log(widgets);

    // Call the server to do the reorder
    $.ajax({
        type:"post",
        url: url,
        cache:false,
        dataType:"json",
        data:{
            "widgets": widgets
        },
        success: [
            XiboSubmitResponse,
            afterDesignerSave
        ]
    });
};
function afterDesignerSave() {
    // Region Preview Refresh
    $('.regionPreview').each(function(idx, el) {
        refreshPreview($(el).attr("regionid"));
    });

    // Layout Status
    layoutStatus(layout.data('statusUrl'));
}

var LibraryAssignSubmit = function() {
    // Collect our media
    var media = [];
    $("#LibraryAssignSortable > li").each(function() {
        media.push($(this).data().mediaId);
    });

    assignMediaToPlaylist($("#LibraryAssign").data().url, media);
};

var assignMediaToPlaylist = function(url, media) {
    toastr.info(media, "Assign Media to Playlist");

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {media: media},
        success: XiboSubmitResponse
    });
};

function layoutStatus(url) {

    // Call with AJAX
    $.ajax({
        type: "get",
        url: url,
        cache: false,
        dataType: "json",
        success: function(response){

            var status = $("#layout-status");

            // Was the Call successful
            if (response.success) {

                // Expect the response to have a message (response.html)
                //  a status (1 to 4)
                //  a duration
                var element = $("<span>").addClass("fa");

                if (response.extra.status == 1) {
                    status.removeClass("alert-warning alert-info alert-danger").addClass("alert-success");
                    element.removeClass("fa-question fa-cogs fa-times").addClass("fa-check");
                }
                else if (response.extra.status == 2) {
                    status.removeClass("alert-success alert-info alert-danger").addClass("alert-warning");
                    element.removeClass("fa-check fa-cogs fa-times").addClass("fa-question");
                }
                else if (response.extra.status == 3) {
                    status.removeClass("alert-success alert-warning alert-danger").addClass("alert-info");
                    element.removeClass("fa-question fa-check fa-times").addClass("fa-cogs");
                }
                else {
                    status.removeClass("alert-success alert-info alert-warning").addClass("alert-danger");
                    element.removeClass("fa-question fa-cogs fa-check").addClass("fa-times");
                }

                if (response.extra.status == 1) {
                    $("#action-tab").find("i").removeClass('fa-bell fa-check fa-times').addClass('fa-check');
                }
                else if (response.extra.status == 2) {
                    $("#action-tab").find("i").removeClass('fa-check fa-times').addClass('fa-bell');
                }
                else if (response.extra.status == 3) {
                    $("#action-tab").find("i").removeClass('fa-check fa-times').addClass('fa-bell');
                }
                else  {
                    $("#action-tab").find("i").removeClass('fa-bell fa-check fa-times').addClass('fa-times');
                }


                if (response.extra.status == 1) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else if (response.extra.status == 2) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else if (response.extra.status == 3) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else  {
                    $("#schedule-btn").find("i").removeClass('fa-clock-o').addClass('fa-times');
                }

                var html = response.html;

                if (response.extra.statusMessage != undefined) {
                    $.each(response.extra.statusMessage, function (index, value) {
                        html += '<br/>' + value;
                    });
                }

                status.html(" " + html).prepend(element);

                // Duration
                $("#layout-duration").html(moment().startOf("day").seconds(response.extra.duration).format("HH:mm:ss"));
                $.each(response.extra.regionduration, function(index, value) 
                {
                    //console.log('idx[' + index + '] = ' + value);
                    var regionid = '#region_' + index;
                    $(regionid).attr('duration', value);
                });
            }
            else {
                // Login Form needed?
                if (response.login) {

                    LoginBox(response.message);

                    return false;
                }
            }

            return false;
        }
    });
}