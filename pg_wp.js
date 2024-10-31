
jQuery(window).resize(pgResize);
jQuery(document).ready(pgResize);

function pgResize() {
    var jq = jQuery("#psygraph");
    if(jq.length) {
        jQuery("html").css("overflow-x", "hidden");
        jQuery("html").css("overflow-y", "hidden");
        jQuery("body").css("overflow-x", "hidden");
        jQuery("body").css("overflow-y", "hidden");
        
        var headerStuff  = jQuery("#masthead").height();
        headerStuff     += jQuery("#wpadminbar").height();
        var windowHeight = jQuery(window).height(); // window.innerHeight;
        var h = windowHeight - headerStuff;
        jq.height(h);
    }
}

function initializeCanvas(canvas, height, width) {
    var h = window.innerHeight - jQuery("#masthead").height() - jQuery("#wpadminbar").height();
    //jQuery("#"+canvas).height(h);
    jQuery("#"+canvas).height(height);
    jQuery("#"+canvas).width(width);
    jQuery("#"+canvas).css("overflow", "hidden");    
    //jQuery(".vis-timeline").css("height", "360px");
}

function drawJSON(jdata, ND, field, element, height, width) {
    var arr  = JSON.parse(jdata);
    var data = new vis.DataSet([]);
    for (var i=1; i < arr.length; i++) {
        if (field=="acceleration") {
            if(ND==2) {
	            var norm = arr[i][1]*arr[i][1];
	            norm += arr[i][2]*arr[i][2];
	            norm += arr[i][3]*arr[i][3];
	            norm = Math.sqrt(norm);
	            data.add({ x: new Date(arr[i][0]),
		                   y: norm});
            }
            else if(ND==3) {
                data.add({x:arr[i][1],
                          y:arr[i][2],
                          z:arr[i][3]});
            }
        }
        else {
            //error;
        }
    }
    // Create the graph
    var options = {
        showCurrentTime: true,
        height: height,
        width:  width,
	interpolation: false,
	dataAxis: {left: {format: axisLabelFormat}}
    };
    if(ND==2) {
        if(data.length==0)
            data.add({x:new Date(), y:0});
        var graph2d = new vis.Graph2d(document.getElementById(element), data, options);
        graph2d.redraw();
        graph2d.fit();
    }
    else if(ND==3) {
        if(data.length==0)
            data.add({x:0, y:0, z:0});
        var graph3d = new vis.Graph3d(document.getElementById(element), data, options);
    }
}
function axisLabelFormat(x) {
    return x.toFixed(3);
}
function drawCSV(csv, element) {
    var csvArray = csv2array(csv);
    var data = new vis.DataSet();
    var type = "acceleration";
    for (var i = 1; i < csvArray.length; i++) {
        if (type=="acceleration") {
            data.add({x:csvArray[i][1],
                      y:csvArray[i][2],
                      z:csvArray[i][3]});
        }
        else {
            //error;
        }
    }
    // Create the graph
    var options = {};
    var graph = new vis.Graph3d(document.getElementById(element), data, options);
}
function csv2array(data) {
    var arr = [];
    var allLines = data.split(/\r\n|\n/);
    var headings = allLines[0].split(',');
    arr.push(headings);
    for(var i=1; i<allLines.length; i++) {
        var a = allLines[i].split(',');
        arr.push( [ parseInt(a[0]), parseFloat(a[1]), parseFloat(a[2]), parseFloat(a[3]) ] );
    }
    return arr;
}
