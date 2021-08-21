<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
?>
<script type="text/javascript">
leftToPay = '%LEFT_TO_PAY%';
totalAmount = '%TOTAL_AMOUNT%';
payInFullDiscount = '%DISCOUNT%';
$(document).ready(function() {
	if (totalAmount != leftToPay) {
		$("#amount").val(leftToPay);
		$("#pay_in_full").attr("disabled", "disabled");
		$("#amount").attr("readonly", "");
	} else {
		$("#pay_in_full").attr("disabled", "");
		$("#pay_in_full").attr("checked", "checked");
		$("#amount").attr("readonly", "readonly");
		amountWDiscount = parseFloat(totalAmount - ((totalAmount * payInFullDiscount) / 100));
		amountWDiscount = amountWDiscount.toFixed(2);
		$("#amount").val(amountWDiscount);
	}

	setAmounts();
	
	$("#pay_in_full").click(function() {
		setAmounts();
	});
});

function setAmounts() {
	if ($("#pay_in_full").is(':checked')) {
		$("#amount").val(amountWDiscount);
		$("#amount").attr("readonly", "readonly");
	} else {
		$("#amount").val(leftToPay);
		$("#amount").attr("readonly", "");
	}
}
</script>
<form name="addPayment" action="%FORM_URL%/?type=%LEAD_TYPE%&id=%LEAD_ID%&qid=%QUOTE_ID%&from=leadsheet" method="post">
<table width="300" border="0" cellspacing="0" class="tblStyle2" style="margin: auto;">
 <tbody style=" border:0;">
  <tr>
    <td width="40%"><div align="right">Amount Paid</div></td>
    <td width="60%"><div align="left">
      <input name="amount" type="text" id="amount" style="width:40px;text-align:center" value="" />
      <input type="checkbox" type="checkbox" id="pay_in_full" name="pay_in_full" value="1" style="width:auto" /> &nbsp;Pay in full
          </div></td>
  </tr>
  <tr>
    <td><div align="right">Method</div></td>
    <td>
	<select name="method" id="method" style="font-size:11px; width:80px;" onchange="showHidePaymentMethod()">
        <option value="none" selected="selected">(choose)</option>
        <option value="Cash">Cash</option>
        <option value="Credit Card">Credit Card</option>
        <option value="Check">Check</option>
        <option value="PayPal">PayPal</option>
      </select>    </td>
  </tr>
<tr align="left">
    <td colspan="2">
      <div align="left">
        <table width="98%" border="0" id="ccMethod" style="border:0; border-collapse:separate; display:none">
          <tr>
            <td width="45%" scope="col"><div align="right">CC Number</div></td>
            <td width="55%" scope="col"><div align="left">
              <input name="cc_number" type="text" id="cc_number" />
            </div></td>
          </tr>
          <tr>
            <td><div align="right">Expiration Date</div></td>
            <td>
<div align="left">
                          <select name="cc_month" id="cc_month" style="font-size:11px; width:40px;">
                            <option value="empty">--</option>
                            <option value="01">01</option>
                            <option value="02">02</option>
                            <option value="03">03</option>
                            <option value="04">04</option>
                            <option value="05">05</option>
                            <option value="06">06</option>
                            <option value="07">07</option>
                            <option value="08">08</option>
                            <option value="09">09</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
                          </select>
                          <select name="cc_year" id="cc_year" style="font-size:11px; width:40px;">
                            <option value="empty">--</option>
                            <option value="09">09</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
                            <option value="13">13</option>
                            <option value="14">14</option>
                            <option value="15">15</option>
                            <option value="15">16</option>
                            <option value="15">17</option>
                            <option value="15">18</option>
                            <option value="15">19</option>
                          </select>
                    </div>
            </td>
          </tr>
            </table>
    
    <table width="98%" border="0" id="checkMethod" style="border:0; border-collapse:separate; display:none">
          <tr>
            <td width="45%" scope="col"><div align="right">Check Number</div></td>
            <td width="55%" scope="col"><div align="left">
              <input name="check_number" type="text" id="check_number" />
            </div></td>
          </tr>
            </table>
            <table width="98%" border="0" id="paypalMethod" style="border:0; border-collapse:separate; display:none">
          <tr>
            <td width="45%" scope="col"><div align="right">PayPal Email</div></td>
            <td width="55%" scope="col"><div align="left">
              <input name="paypal_acc" type="text" id="paypal_acc" />
            </div></td>
          </tr>
            </table>
      </div></td>
  </tr>
  <tr>
    <td><div align="right">Date</div></td>
    <td><div align="left">
      <input type="text" name="date" id="date" style="width:70px; text-align:center" readonly="readonly" />
      <img src="../../images/icons/calendar.png" name="date_button" width="10" height="10" id="date_button" style="cursor:pointer; " />
      <script type="text/javascript">
        Calendar.setup({
            inputField     :    "date",
            ifFormat       :    "%m/%d/%Y",
            button         :    "date_button",
            singleClick    :    false,
			align		   : 	'BR',
            step           :    1
        });
   		</script>
    </div></td>
  </tr>
  <tr>
    <td colspan="2"><div align="center">
      <input type="submit" name="button" id="button" value="Submit" style="text-align:center; width:auto" />
    </div></td>
    </tr>
 </tbody>
</table>
</form>