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

function configureDragAndDrop() {

    // Do we want to bind?
    if (lockPosition || lowDesignerScale) {
        layout.find(".region").draggable("disable").resizable("disable");
    } else {
        layout.find(".region").draggable("enable").resizable("enable");
    }
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
                "margin-top": position.top + $('#timelineControl').closest('.modal-body').scrollTop()
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
var XiboTimelineSaveOrder = function(timelineDiv) {

    var url = $("#" + timelineDiv).data().orderUrl;
    var i = 0;
    var widgets = {};

    $('#' + timelineDiv + ' li.timelineMediaListItem').each(function() {
        i++;
        widgets[$(this).attr("widgetid")] = i;
    });

    console.log(widgets);


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
