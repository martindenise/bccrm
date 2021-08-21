function updateChrsLeft() {
	//alert($("#objective").val());
	var str = $("#sms_message").val();
	var length = stringLength(str.toString());
	if (length > 160) {
		$("#sms_message").val($("#sms_message").val().substr(0,160));
	}

	$("#sms_message_chrs_left").html((160-length+1) + ' chrs. left');
}

function stringLength(string) {
	browser=navigator.appName;
	if (browser=="Microsoft Internet Explorer") {
		return string.length;
	}
	else {
		var i=0;
		var p=0;
		while (string[i]!=undefined) {
			if (string[i]=="\n") p=p+2;
			else p=p+1;
			i=i+1;
		}
		return p;
	}
}

function showHidePaymentMethod() {
	if ($("#method").val() == 'Credit Card') {
		$("#checkMethod").fadeOut(500, function() { $("#ccMethod").fadeIn(500);} );
	}
	if ($("#method").val() == 'Check') {
		$("#ccMethod").fadeOut(500, function() { $("#checkMethod").fadeIn(500);} );
	}
	if ($("#method").val() == 'PayPal') {
		$("#ccMethod").fadeOut(500, function() { $("#paypalMethod").fadeIn(500);} );
	}
	if ($("#method").val() == 'none' || $("#method").val() == 'Cash') {
		$("#checkMethod").fadeOut(500);
		$("#ccMethod").fadeOut(500);
	}
}


function showHideReportsBy(show) {
	if (show == 'school') {
		$("#sales_person").fadeOut(500, function() { $("#school").fadeIn(500);} );
	}
	if (show == 'sales_person') {
		$("#school").fadeOut(500, function() { $("#sales_person").fadeIn(500);});
	}
}

function showHidePhoneType(show) {
	if (show == 'Phone') {
		$("#phoneSMS").fadeOut(500, function() { $("#phoneCall").fadeIn(500);} );
	}
	if (show == 'SMS') {
		$("#phoneCall").fadeOut(500, function() { $("#phoneSMS").fadeIn(500);});
	}
}
function showHideContactMethod() {
	if ($("#ctype").val() == 'Phone') {
		$("#emailMethod").fadeOut(500, function() { $("#phoneMethod").fadeIn(500);} );
	}
	if ($("#ctype").val() == 'Email') {
		$("#phoneMethod").fadeOut(500, function() { $("#emailMethod").fadeIn(500);} );
	}
	if ($("#ctype").val() == 'none') {
		$("#phoneMethod").fadeOut(500);
		$("#emailMethod").fadeOut(500);
	}
}

function showDeleteReason(deleteLink,userId) {
	// hide balloon div
	hideDeleteReason();

	// set the new coordinates, the message and show the div
	$("#balloontip_dialog").css('top',(mouseY+5)+'px');
	$("#balloontip_dialog").css('left',(mouseX-170)+'px');
	$("#balloontip_dialog").css('display','inline');

	$("#goRemove").click(function() {
			var reasonStr = $("#reason").val();
			deleteLink = deleteLink.replace('new_leads','newleads');
			
			window.location = deleteLink + '?id=' + userId + '&reason=' + escape(reasonStr);
	});
}
function hideDeleteReason() {
	$("#balloontip_dialog").css('display','none');
	$("#goRemove").click(function () {  });
}

var IE = document.all?true:false;
if (!IE) document.captureEvents(Event.MOUSEMOVE);
document.onmousemove = getMouseXY;
var mouseX = 0
var mouseY = 0

function getMouseXY(e) {
  if (IE) {
    mouseX = event.clientX + document.body.scrollLeft;
    mouseY = event.clientY + document.body.scrollTop;
  } else {
    mouseX = e.pageX;
    mouseY = e.pageY;
  }
  if (mouseX < 0){mouseX = 0}
  if (mouseY < 0){mouseY = 0}
  return true;
}


function doDeleteConfirm() {
	return confirm('Are you sure you want to delete this ?');
}

function doCancelConfirm() {
	return confirm('Are you sure you want to cancel this confirmation request ?');
}

function calcTotal(formHandle) {
	var total = 0;
	total = total + (1*formHandle.tuition.value) + (1*formHandle.app_fee.value) + (1*formHandle.extras.value);
	totalString = String(total);
	if (totalString.indexOf('.') >= 0)
		total = parseFloat(totalString.substr(0,totalString.indexOf('.')+3));
	else
		total = parseFloat(totalString)+'.00';
	formHandle.total_amount.value = total;
}

function calcDue(formHandle) {
/*	due = formHandle.total_amount.value - formHandle.paid_amount.value;
	dueString = String(due);
	if (dueString.indexOf('.') >= 0)
		due = parseFloat(dueString.substr(0,dueString.indexOf('.')+3));
	else
		due = parseFloat(dueString)+'.00';
	formHandle.amount_due.value = due;*/
}

function setDefaultValue(handle,defaultValue) {
	if (handle)
		if (handle.value == '')
			handle.value = defaultValue;
}

function setDate() {
	divHdl = document.getElementById('today');
	if (divHdl) {
		var days = new Array();
		var months = new Array();
		days[0] = 'Sunday';days[1] = 'Monday';days[2] = 'Tuesday';days[3] = 'Wednesday';days[4] = 'Thursday';days[5] = 'Friday';days[6] = 'Saturday';
		months[0] = 'January';months[1] = 'February';months[2] = 'March';months[3] = 'April';months[4] = 'May';months[5] = 'June';months[6] = 'July';months[7] = 'August';months[8] = 'September';months[9] = 'October';months[10] = 'November';months[11] = 'December';

		var time = new Date();
		dateString = days[time.getDay()] + ', ' + time.getDate() + ' ' + months[time.getMonth()] + ' ' + time.getFullYear();

		divHdl.innerHTML = dateString;
	}
	setTime();
}
function setTime(){var clock_time = new Date();var clock_hours = clock_time.getHours();var clock_minutes = clock_time.getMinutes();var clock_seconds = clock_time.getSeconds();var clock_suffix = "AM";if (clock_hours > 11){clock_suffix = "PM";clock_hours = clock_hours - 12;}if (clock_hours == 0){clock_hours = 12;}if (clock_hours < 10){clock_hours = "0" + clock_hours;}if (clock_minutes < 10){clock_minutes = "0" + clock_minutes;}if (clock_seconds < 10){clock_seconds = "0" + clock_seconds;}var clock_div = document.getElementById('time');clock_div.innerHTML = clock_hours + ":" + clock_minutes + " " + clock_suffix;
setTimeout("setTime()", 1000);
}
