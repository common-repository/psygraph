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

function drawJSON(jarr, display, element, height, width) {
    var arr = JSON.parse(jarr);
    var data = new vis.DataSet([]);
    //var groups = new vis.DataSet();
    // Create the graph
    var options = {
        showCurrentTime: true,
        height: height,
        width:  width,
        interpolation: false,
        dataAxis: {left: {format: axisLabelFormat}}
    };
    var ND = arr[0].length;
    var graph;
    if(ND==2 || display=="bar") {
        if(display=="bar") {
            options.style ='bar';
            options.barChart = {width:50, align:'center'};
            options.drawPoints = false;
        }
        /*if(display=="bar" && ND>2) { // interpret the data as 2D plus a group
            groups.add({id: 0, content: "Correct"});
            groups.add({id: 1, content: "Incorrect"});
            for (var i=1; i < arr.length; i++) {
                data.add({ x: new Date(arr[i][0]),
                            y: arr[i][1],
                            group: arr[i][2]});
            }
        }
        else {*/
        for (var i=1; i < arr.length; i++) {
            data.add({ x: new Date(arr[i][0]),
                        y: arr[i][1]});
        }
        //}
        if(data.length==0)
            data.add({x:new Date(), y:0});
        graph = new vis.Graph2d(document.getElementById(element), data, options);
    }
    else if(ND==4) {
        for (var i=1; i < arr.length; i++) {
            data.add({  x:arr[i][1],
                        y:arr[i][2],
                        z:arr[i][3]});
        }
        if(data.length==0)
            data.add({x:0, y:0, z:0});
        graph = new vis.Graph3d(document.getElementById(element), data, options);
    }
    else {
        exit(13);
    }
    graph.redraw();
    if(ND != 4)
        graph.fit();
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
