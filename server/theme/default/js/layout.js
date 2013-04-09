/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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


/**
 * Load timeline callback
 */
var LoadTimeLineCallback = function() {

    // Refresh the preview
    var preview = Preview.instances[$('#timelineControl').attr('regionid')];
    preview.SetSequence(preview.seq);

    $("li.timelineMediaListItem").hover(function() {

        var position = $(this).position();

        //Change the hidden div's content
        $("div#timelinePreview").html($("div.timelineMediaPreview", this).html()).css("margin-top", position.top + $('#timelineControl').scrollTop()).show();

    }, function() {
        return false;
    });

    $(".timelineSortableListOfMedia").sortable();
}


var XiboTimelineSaveOrder = function(mediaListId, layoutId, regionId) {

    //console.log(mediaListId);

    // Load the media id's into an array
    var mediaList = "";

    $('#' + mediaListId + ' li.timelineMediaListItem').each(function(){
        mediaList = mediaList + $(this).attr("mediaid") + "&" + $(this).attr("lkid") + "|";
    });

    //console.log("Media List: " + mediaList);

    // Call the server to do the reorder
    $.ajax({
        type:"post",
        url:"index.php?p=timeline&q=TimelineReorder&layoutid="+layoutId+"&ajax=true",
        cache:false,
        dataType:"json",
        data:{
            "regionid": regionId,
            "medialist": mediaList
        },
        success: XiboSubmitResponse
    });
}

/**
 * Library Assignment Form Callback
 */
var LibraryAssignCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#LibraryAssignTable .library_assign_list_select").click(function(){
        // Get the row that this is in.
        var row = $(this).parent().parent();

        // Construct a new list item for the lower list and append it.
        $("<li/>", {
            text: row.attr("litext"),
            id: row.attr("rowid"),
            "class": "li-sortable",
            dblclick: function(){
                $(this).remove();
            }
        })
        .appendTo("#LibraryAssignSortable");

        // Add a span to that new item
        $("<span/>", {
            text: " [x]",
            click: function(){
                $(this).parent().remove();
            }
        })
        .appendTo("#" + row.attr("rowid"));

    });

    $("#LibraryAssignSortable").sortable().disableSelection();
}

var LibraryAssignSubmit = function(layoutId, regionId)
{
    // Serialize the data from the form and call submit
    var mediaList = $("#LibraryAssignSortable").sortable('serialize');

    mediaList = mediaList + "&regionid=" + regionId;

    //console.log(mediaList);

    $.ajax({
        type: "post",
        url: "index.php?p=timeline&q=AddFromLibrary&layoutid="+layoutId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: mediaList,
        success: XiboSubmitResponse
    });
}

var background_button_callback = function()
{
	//Want to attach an onchange event to the drop down for the bg-image
	var id = $('#bg_image').val();

	$('#bg_image_image').attr("src", "index.php?p=module&q=GetImage&id=" + id + "&width=80&height=80&dynamic");
}

var text_callback = function()
{
    // Conjure up a text editor
    $("#ta_text").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_text").ckeditorGet().destroy();
        $("#div_dialog").unbind("dialogclose.xibo");
    });

    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    // Min width
    if (width < 800) width = 800;

    // Adjust the width and height
    width = width + 80;
    height = height + 295;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');
    
    $("#cke_contents_ta_text iframe").contents().find("body").css("background-color", $("#layout").css("background-color"));

    return false; //prevent submit
}

var microblog_callback = function()
{
    // Conjure up a text editor
    $("#ta_template").ckeditor();
    $("#ta_nocontent").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_template").ckeditorGet().destroy();
        $("#ta_nocontent").ckeditorGet().destroy();

        $("#div_dialog").unbind("dialogclose.xibo");
    })
    
    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    //Min width
    if (width < 800) width = 800;
    height = height - 170;

    // Min height
    if (height < 300) height = 300;

    width = width + 80;
    height = height + 480;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');

    return false; //prevent submit
}

var datasetview_callback = function()
{
    $("#columnsIn, #columnsOut").sortable({
		connectWith: '.connectedSortable',
		dropOnEmpty: true
	}).disableSelection();

    return false; //prevent submit
}

var DataSetViewSubmit = function() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#ModuleForm").attr('action') + "&ajax=true";

    // Get the two lists
    serializedData = $("#columnsIn").sortable('serialize') + "&" + $("#ModuleForm").serialize();

    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });

    return;
}

$(document).ready(function() {
	
	var container = document.getElementById('layout');
	
	$('.region').draggable({
            containment:container,
            stop:function(e, ui){
                //Called when draggable is finished
                submitBackground(this);
            },
            drag: updateRegionInfo
        }).resizable({
            containment:container,
            minWidth:25,
            minHeight:25,
            stop:function(e, ui){
                //Called when resizable is finished
                submitBackground(this);
            },
            resize: updateRegionInfo
        }).contextMenu('regionMenu', {
	    bindings: {
                'btnTimeline': function(t) {
                    XiboFormRender($(t).attr("href"));
	        },
                'options' : function(region) {
                    var width 	= $(region).attr("width");
                    var height 	= $(region).attr("height");
                    var top 	= $(region).css("top");
                    var left 	= $(region).css("left");
                    var regionid = $(region).attr("regionid");
                    var layoutid = $(region).attr("layoutid");
                    var scale = $(region).attr("scale");

                    var layout = $('#layout');

                    XiboFormRender("index.php?p=timeline&q=ManualRegionPositionForm&layoutid="+layoutid+"&regionid="+regionid+"&top="+top+"&left="+left+"&width="+width+"&height="+height+"&layoutWidth="+layout.width()+"&layoutHeight="+layout.height()+"&scale="+scale);
                },
		'deleteRegion': function(t) {
	            deleteRegion(t);
	        },
		'setAsHomepage': function(t) {
                    var layoutid = $(t).attr("layoutid");
                    var regionid = $(t).attr("regionid");

	            XiboFormRender("index.php?p=timeline&q=RegionPermissionsForm&layoutid="+layoutid+"&regionid="+regionid);
	        }
            }
	});
	
	$('#layout').contextMenu('layoutMenu', {
		bindings: {
			'addRegion': function(t){
				addRegion(t);
			},
			'editBackground': function(t) {
				XiboFormRender($('#background_button').attr("href"));
			},
			'layoutProperties': function(t) {
				XiboFormRender($('#edit_button').attr("href"));
			},
			'templateSave': function(t) {
				var layoutid = $(t).attr("layoutid");
			
				XiboFormRender("index.php?p=template&q=TemplateForm&layoutid="+layoutid);
			}
		}
	});
	
	
	// Preview
	$('.regionPreview').each(function(){
            new Preview(this);
	});

        // Aspect ration option
       $('#lockAspectRatio').change(function(){
            var opt = $('#lockAspectRatio').val();

            if (opt == "on") {
                alert("on");
                $('.region').resizable('option', 'aspectRatio', true);
            }
            else {
                $('.region').resizable('option', 'aspectRatio', false);
            }
       });
       
       // Set the height of the grid to be something sensible for the current screen resolution
       $('#LayoutJumpList .XiboGrid').css("height", $(window).height() - 200);
        
       $('#JumpListHeader').click(function(){
           if ($('#JumpListOpenClose').html() == "^")
               $('#JumpListOpenClose').html("v");
           else
               $('#JumpListOpenClose').html("^");
           
           $('#' + $(this).attr('JumpListGridID')).slideToggle("slow", "swing");
       });
});

/*
 * Updates the Region Info
 */
function updateRegionInfo(e, ui) {
    var pos = $(this).position();
    var scale = $(this).attr("scale");
    $('.regionInfo', this).html(Math.round($(this).width() * scale, 0) + " x " + Math.round($(this).height() * scale, 0) + " (" + Math.round(pos.left * scale, 0) + "," + Math.round(pos.top * scale, 0) + ")");
}

/**
 * Adds a region to the specified layout
 * @param {Object} layout
 */
function addRegion(layout)
{
	var layoutid = $(layout).attr("layoutid");
	
	$.ajax({type:"post", url:"index.php?p=timeline&q=AddRegion&layoutid="+layoutid+"&ajax=true", cache:false, dataType:"json",success: XiboSubmitResponse});
}

/**
 * Submits the background changes from draggable / resizable
 * @param {Object} region
 */
function submitBackground(region)
{
	var width 	= $(region).css("width");
	var height 	= $(region).css("height");
	var top 	= $(region).css("top");
	var left 	= $(region).css("left");
	var regionid = $(region).attr("regionid");
	var layoutid = $(region).attr("layoutid");

    // Update the region width / height attributes
    $(region).attr("width", width).attr("height", height);

    // Update the Preview for the new sizing
    var preview = Preview.instances[regionid];
    preview.SetSequence(preview.seq);
	
	$.ajax({type:"post", url:"index.php?p=timeline&q=RegionChange&layoutid="+layoutid+"&ajax=true", cache:false, dataType:"json", 
		data:{"width":width,"height":height,"top":top,"left":left,"regionid":regionid},success: XiboSubmitResponse});
}

/**
 * Deletes a region
 */
function deleteRegion(region) {
	var regionid = $(region).attr("regionid");
	var layoutid = $(region).attr("layoutid");

	XiboFormRender("index.php?p=timeline&q=DeleteRegionForm&layoutid="+layoutid+"&regionid="+regionid);
}

/**
 * Handles the tRegionOptions trigger
 */
function tRegionOptions() {
    var regionid = gup("regionid");
    var layoutid = gup("layoutid");
	
    XiboFormRender('index.php?p=timeline&layoutid='+layoutid+'&regionid='+regionid+'&q=RegionOptions');
}

function setFullScreenLayout() {
    $('#width', '.XiboForm').val($('#layoutWidth').val());
    $('#height', '.XiboForm').val($('#layoutHeight').val());
    $('#top', '.XiboForm').val('0');
    $('#left', '.XiboForm').val('0');
}

function transitionFormLoad() {
    $("#transitionType").change(transitionSelectListChanged);
    
    // Fire once for initialisation
    transitionSelectListChanged();
}

function transitionSelectListChanged() {
    // See if we need to disable any of the other form elements based on this selection
    var selectionOption = $("#transitionType option:selected");
    
    if (!selectionOption.hasClass("hasDuration"))
        $("tr.transitionDuration").hide();
    else
        $("tr.transitionDuration").show();
        
    if (!selectionOption.hasClass("hasDirection"))
        $("tr.transitionDirection").hide();
    else
        $("tr.transitionDirection").show();
}

function Preview(regionElement)
{
	// Load the preview - sequence 1
	this.seq = 1;
	this.layoutid = $(regionElement).attr("layoutid");
	this.regionid = $(regionElement).attr("regionid");
	this.regionElement	= regionElement;
	this.width	= $(regionElement).width();
	this.height = $(regionElement).height();
	
	var regionHeight = $(regionElement).height();
	var arrowsTop = regionHeight / 2 - 28;
	var regionid = this.regionid;
	
	this.previewElement = $('.preview',regionElement);
	this.previewContent = $('.previewContent', this.previewElement);

	// Setup global control tracking
	Preview.instances[this.regionid] = this;
	
	// Create the Nav Buttons
	$('.previewNav',this.previewElement)	
		.append("<div class='prevSeq' style='position:absolute; left:1px; top:"+ arrowsTop +"px'><img src='theme/default/img/arrow_left.gif' /></div>")
		.append("<div class='nextSeq' style='position:absolute; right:1px; top:"+ arrowsTop +"px'><img src='theme/default/img/arrow_right.gif' /></div>");

	$('.prevSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionid];
		var maxSeq 	= $('#maxSeq', preview.previewContent[0]).val();
				
		var currentSeq = preview.seq;
		currentSeq--;
		
		if (currentSeq <= 0)
		{
			currentSeq = maxSeq;
		}
		
		preview.SetSequence(currentSeq);
	});
	
	$('.nextSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionid];
		var maxSeq 	= $('#maxSeq', preview.previewContent[0]).val();
		
		var currentSeq = preview.seq;
		currentSeq++;
		
		if (currentSeq > maxSeq)
		{
			currentSeq = 1;
		}
		
		preview.SetSequence(currentSeq);
	});	
	
	this.SetSequence(1);
}

Preview.instances = {};

Preview.prototype.SetSequence = function(seq)
{
	this.seq = seq;
	
	var layoutid 		= this.layoutid;
	var regionid 		= this.regionid;
	var previewContent 	= this.previewContent;

	this.width	= $(this.regionElement).width();
	this.height = $(this.regionElement).height();
	
	// Get the sequence via AJAX
	$.ajax({type:"post", 
		url:"index.php?p=timeline&q=RegionPreview&ajax=true", 
		cache:false, 
		dataType:"json", 
		data:{"layoutid":layoutid,"seq":seq,"regionid":regionid,"width":this.width, "height":this.height},
		success:function(response) {
		
			if (response.success) {
				// Success - what do we do now?
				$(previewContent).html(response.html);
			}
			else {
				// Why did we fail? 
				if (response.login) {
					// We were logged out
		            LoginBox(response.message);
		            return false;
		        }
		        else {
		            // Likely just an error that we want to report on
		            $(previewContent).html(response.html);
		        }
			}
			return false;
		}
	});
}