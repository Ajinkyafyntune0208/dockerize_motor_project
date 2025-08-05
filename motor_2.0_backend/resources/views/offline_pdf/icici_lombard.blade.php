<!DOCTYPE html>
<html>

<head>
    <title>#show_company_name#</title>
    {{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> --}}
    <link rel="stylesheet" type="text/css"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    {{-- <script type="text/javascript" src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> --}}
</head>
<style>
    #wrapper {
        width: 64%;
        height: auto;
        margin: 0px auto;
        border: double;
        font-family: 'Helvetica', serif;
        font-size: 13px;
        padding: 5px;
    }

    #wrapper1 {
        /* width: 64%; */
        width: 100%;
        height: 1221px;
        margin: 0px auto;
        border: solid;
        font-family: 'Helvetica', serif;
        font-size: 13px;
        padding: 5px;
        margin-bottom: -9px;
    }

    .tata-comapny-details {
        text-align: center;
        font-weight: bold;
    }

    table {
        width: 100%;
    }

    th {
        text-align: left;
    }

    hr.bold-line {
        border: 1px solid black;
    }

    .column {
        float: left;
        width: 50%;
        padding: 20px;
    }

    /* Clear floats after the columns */
    .row:after {
        content: "";
        display: table;
        clear: both;
    }

    .italic-font {
        font-family: 'Times-Roman', serif;
        font-style: italic;
        font-size: 13px;
    }

    th {
        font-size: 12px;
    }

    td {
        font-size: 12px;
    }

</style>

<body>
    {{-- <div id="wrapper1 d-none">
        <center><img src="http://motoraffinity.fynity.in/allience/quatepage/img/thanksimg/0001.jpg"
                style="width:100%; height: 900px; padding: 5px;"></center>
        <br><br>
    </div> --}}
    <div id="wrapper1">
        <span style="float: right;font-size: 12px;">Policy No: #show_policy_no# </span> <span
            style="font-size: 10px;">#created_at#</span><br>
        <div style="border:1px solid black !important;">
            <div class="row">
                <div class="col-sm-4" style="width: 25%;">
                    <img style="width: 130px; margin-top: 20px;"
                        src="#show_company_logo#">
                </div>
                <div class="clearfix"></div>
                <div class="col-sm-8" style="text-align: center;padding: 5px; width: 75%;float:right;">
                    <b style="font-size: 12px;">TATA AIG GENERAL INSURANCE COMPANY LTD</b><br>
                    <span style="font-weight: bold;font-size:10px;">2nd Floor, City Tower, Next to Mahatma Gandhi
                        Hospital,Dr. S. S. Rao Road, Parel – East</span><br>
                    <span style="font-weight: bold;font-size: 12px;">IRDA Regn. No.108</span><br>
                    <!--    <span style="font-weight: bold;font-size: 12px;">CIN No : #cin_no# Toll Free No. #show_ic_contact#</span><br> -->
                    <span style="font-weight: bold;font-size: 12px;">#show_product_name# - Package Policy</span><br>
                    <b style="font-size:9px; font-weight: bold;">CERTIFICATE OF INSURANCE CUM POLICY SCHEDULE - Form 51
                        of the central Motors</b><br>
                    <span style="font-weight: bold; font-size: 12px;">Vehicles Rules, 1989</span><br>
                    <span style="font-weight: bold; font-size: 12px;">Policy No: #show_policy_no#</span>
                </div>
            </div>
        </div>
        <hr class="bold-line">
        </hr>
        <div class="row">
            <!--  <table style="margin-left: 20px;">
          <tr>
            <th style="font-size:12px;">DETAILS OF THE POLICY HOLDER</th>
            <td></td>
            <th>POLICY DETAILS</th>
            <td></td>
          </tr>
          <tr>
            <th>Insured Name </th>
            <td>#show_full_name# </td>
            <th>Policy Issuing Office </th>
            <td>#show_ic_address#</td>
          </tr>
          <tr>
            <th>Insured Address </th>
            <td><span>#show_address#</span> <span>#show_state#</span> <span>#show_city#</span> <span>#show_pincode#</span></td>
            <th>Policy Issued On </th>
            <td>#show_policy_issuedon_date#</td>
          </tr>
          <tr>
            <th>Contact No. (s)</th>
            <td>#show_mobile_no#</td>
            <th>Email Address </th>
            <td>#show_email_id#</td>
          </tr>
          <tr>
             <th>Email Address</th>
             <td>#show_email_id#</td>
              #show_nominee_details#
          </tr>
          <tr>
            <th>Broker Code</th>
            <td>#show_broker_code#</td>
            #name_of_financer#
          </tr>
          <tr>
            <th>Broker Name</th>
            <td>Alliance Insurance Broker</td>
            #show_previous_policy_no#
          </tr>
          <tr>
            <th>Broker Telephone No.</th>
            <td>18002669693</td>
            
          </tr>
          <tr>
             <th>Policy Term</th>
            <td><span>#show_policy_start_date#</span> to <span>23:59 Hrs of #show_policy_end_date#</span></td>
            #pre_policy_expiry_date#
          </tr>
           #show_previous_insurance_company#
        </table> -->

            <table style="margin-left: 20px;">
                <tr>
                    <th style="font-size:12px;">DETAILS OF THE POLICY HOLDER</th>
                    <td></td>
                    <th>POLICY DETAILS</th>
                    <td></td>
                </tr>
                <tr>
                    <th>Insured Name</th>
                    <td>#show_full_name#</td>
                    <th>Policy Issuing Office</th>
                    <td>#show_ic_address#</td>
                </tr>
                <tr>
                    <th>Insured Address</th>
                    <td><span>#show_address#</span> <span>#show_state#</span> <span>#show_city#</span>
                        <span>#show_pincode#</span></td>
                    <th>Policy Issued On</th>
                    <td>#show_policy_issuedon_date#</td>
                </tr>
                <tr>
                    <th>Contact No. (s)</th>
                    <td>#show_mobile_no#</td>
                    <th>Policy Term</th>
                    <td><span>#show_policy_start_date#</span> to <span>23:59 Hrs of #show_policy_end_date#</span></td>
                </tr>
                <tr>
                    <th>Email Address</th>
                    <td>#show_email_id#</td>
                    <!-- <th>Hypothecated To</th> -->
                    #name_of_financer#
                </tr>
                <tr>
                    <!-- <th>Nominee Details</th>
            <td>osihar</td> -->
                    #show_nominee_details#
                    <!--  <th>Previous Policy No.</th>
            <td>osihar</td> -->
                    #show_previous_policy_no#
                </tr>
                <tr>
                    <th>Broker Code</th>
                    <td>#show_broker_code#</td>
                    <!-- <th>Previous Insurance Company Name</th>
            <td>osihar</td> -->
                    #show_previous_insurance_company#
                </tr>
                <tr>
                    <th>Broker Name</th>
                    <td><!--Alliance Insurance Broker-->#broker_name#</td>
                    <!-- <th>Previous Policy Expiry date</th>
            <td>osihar</td> -->

                </tr>
                <tr>
                    <th>Broker Telephone No.</th>
                    <td>{{-- 18002669693 --}}#broker_telephone_no#</td>
                    #pre_policy_expiry_date#
                </tr>
            </table>
        </div>
        <br><br><br>
        <div style="w-100">
            <b style="font-size: 12px;">VEHICLE DETAILS</b>
            <table class="w-100">
                <tbody>
                    <tr style="border: 1px solid black; border-collapse: collapse; text-align: center;">
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            Registration No.</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">RTO
                            Location</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Make</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Model</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Variant
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Engine No.
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Chassis No.
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">MFG(Year)
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">CC</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Seating
                            Capacity</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Carrying
                            Capacity</td>
                    </tr>
                    <tr style="border: 1px solid black; border-collapse: collapse; text-align: center;">
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">#show_rto#
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #rto_location#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">#show_make#
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_model#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_varient#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_engin_no#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_chassis_no#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_mfg_year#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">#show_cc#
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_carryring_capacity#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_carryring_capacity#</td>
                    </tr>
                </tbody>
            </table>
            <br><br>
            <b style="font-size: 12px;">INSURED DECLARED VALUE</b>
            <table>
                <tbody>
                    <tr style="border: 1px solid black; border-collapse: collapse;">
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Vehicle IDV
                            (In Rs.)</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Elec.
                            Accessories</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Non-Elec.
                            Accessories</td>
                        <!--<td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">BIFUEL Kit</td>-->
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">BIFUEL Kit
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">Total Value
                            (in Rs.)</td>
                    </tr>
                    <tr style="border: 1px solid black; border-collapse: collapse;">
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">#show_idv#
                        </td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_elec_accessories_amts#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_non_elec_accessories_amt#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #bifuel_kit_value#</td>
                        <td style="border: 1px solid black; border-collapse: collapse; text-align: center; ">
                            #show_gross_idv#</td>
                    </tr>
                </tbody>
            </table>
            <br><br> <br><br>
            <span style="float: right;font-size: 12px;">Policy No: #show_policy_no# </span> <span
                style="font-size: 10px;">#created_at#</span><br>
            <br><br>
            <div class="row">
                <div style="border:1px solid black !important; padding:2px;">
                    <b style="font-size: 12px;">SCHEDULE OF PREMIUM(IN RS.)</b>
                    <table style="margin-left: 20px;">
                        <tr>
                            <th style="text-align: center;">Own Damage(A)</th>
                            <td></td>
                            <th style="text-align: center;">Liability(B)</th>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Basic OD Premium </td>
                            <td>#show_total_own_damage#</td>
                            <th>Basic Third party Liability</th>
                            <td>#show_tppd_premium_amount#</td>
                        </tr>
                        <tr>
                            <th><u>Add</u></th>
                            <td></td>#show_compulsory_pa_own_driver#
                        </tr>
                        <tr>

                            <td>#show_elec_accessories_amt#</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>#show_elec_accessories_amt#</td>#show_cover_unnamed_passenger_value#
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <th>Paid Driver liability</th>
                            <td>#show_lld_paid_driver_liability#</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <th>Bi-Fuel CNG/LPG Kit TP</th>
                            <td>#show_cng_lpg_tp#</td>
                        </tr>

                        <tr>
                            <th><u>Less</u></th>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>#show_antitheft_discount#</td>
                            <th>Total Liability Premium(B) </th>
                            <td>#show_total_liability_premium#</td>
                        </tr>
                        <tr>
                            <td>NCB (#show_ncb_discount#) % </td>
                            <td>#show_deduction_of_ncb#</td>
                            <th>Net Premium(A+B+ADD ON)</th>
                            <td>#show_net_premium#</td>
                        </tr>
                        <tr>
                            <td>Voluntary Discount</td>
                            <td>#show_voluntary_excess#</td>
                            <!--    <th>SGST/UTGST </th>
              <td></td> -->
                        </tr>
                        <!--    <tr>
              <td></td>
              <td></td>
              <th>CGST </th>
              <td></td>
            </tr> -->
                        <tr>
                            <td></td>
                            <td></td>
                            <th>CGST </th>
                            <td>#show_cgst#</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <th>SGST </th>
                            <td>#show_sgst#</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <th>Gross Premium</th>
                            <td>#show_final_premium#</td>
                        </tr>
                        <tr>
                            <th><u>Add-On</u></th>
                        </tr>
                        #addon_breakup#
                        <!--   <span>#show_compulsory_pa_own_driver#</span> -->
                        <tr>
                            <th>Total Own Damage(A) </th>
                            <!--   <td>#show_total_own_amount#</td> -->
                            <td>#show_od_premium#</td>
                        </tr>
                    </table>
                </div>
            </div>
            <br><br>
            <hr class="bold-line">
            </hr>
            <div style="border:1px solid black; padding: 5px;">
                <span style="font-size: 10px;">Geographical Area Extension:India Compulsory Deductibles (IMT-22)
                    :1000.00</span><br>
                <span style="font-size: 12px;">IMT Codes : #show_imt_code#</span>
            </div>
        </div>
    </div>
    <div id="wrapper1">
        <div>
            <b style="margin-top: 30px; font-size: 12px;">LIMITS OF LIABILITY :</b><br>
            <span style="font-size: 12px;">(a)Under Section II - 1 (i) of the policy -> Death of or bodily injury : Such
                amount as is necessary to meet the requirements of the Motor Vehicles Act, 1988.</span><br>
            <span style="font-size: 12px;">(b)Under Section II - 1 (ii) of the policy -> Damage to Third Party Property
                Rs.750,000.00 ; PA Cover for Owner-Driver under section III: CSI Rs.15,00,000.00 ; Voluntary Deductable
                Rs. 0</span><br>
            <b style="font-size: 14px;">LIMITATIONS AS TO USE :</b><br>
            <span style="font-size: 12px;">The policy covers use of the vehicle for any purpose other than : Hire or
                reward, Carriage of goods(other than samples or personal luggage), Organized racing, Pace making, Speed
                testing, Reliability trials,Any purpose in connection with Motor Trade.</span><br>
            <b style="font-size: 12px;">DRIVER'S CLAUSES :</b><br>
            <span style="font-size: 12px;">Any person including the insured : Provided that a person driving holds an
                effective Driving License at the time of the accident and is not disqualified from holding or obtaining
                such a license. Provided also that the person holding an effective Learner's License may also drive the
                vehicle and that such a person satisfies the requirements of Rule 3 of the Central Motor Vehicles Rules,
                1989.</span><br> <br>
            <span style="font-size: 12px;">Under Hire Purchase/Hypothecation/Lease Agreement with :</span><br>
            <span style="font-size: 12px;">Premium Collection Details :-[Amount / ReceiptDate] Rs. <a
                    href="#show_final_premium#">#show_final_premium#.000 </a></span><br>
            <span style="font-size: 12px;">Received with thanks from #show_full_name# an amount of Rs.
                #show_final_premium#.000 towards Insurance Premium. Consolidated Stamp Duty Paid.</span><br>
            <br><br>
            <span style="float: right;font-size: 12px;">Policy No: #show_policy_no# </span> <span
                style="font-size: 10px;">#created_at#</span><br><br><br><br><br><br>
            <b style="font-weight: bold;">Note : Policy cover is subject to realisation of cheque.</b><br>
            <span style="font-size: 10px; font-weight: bold;">WARNING THAT IN CASE OF DISHONOUR OF THE PREMIUM CHEQUE ,
                THIS DOCUMENT STANDS AUTOMATICALLY CANCELLED 'AB-INITIO'<br> I/We hereby certify that the policy to
                which the certificate relates as well as the certificate of insurance are issued in accordance with the
                provision of chapter X,XI of M.V.Act 1988</span><br>
            <!--    <p style="font-size: 12px;">Insurance Company PAN No. : AABCH0738E</p>
          <p style="font-size: 12px;">Insurance Company Branch GSTIN : 27AABCT3518Q1ZW</p>
          <p style="font-size: 12px;">State GSTIN Code Of Insurance Company : 27 - Maharashtra</p> -->
            <span style="font-size: 12px;">
                Insurance Company PAN No. : #insurance_company_pan_no#<br>
                Insurance Company Branch GSTIN : #insurance_company_branch_gstin#
                <!-- #insurance_company_branch_gstin# --><br>
                State GSTIN Code Of Insurance Company : 27 - Maharashtra<br>
                Service Accounting Code : 997134

            </span>
            <div class="row">
                <div class="column">
                    <span style="font-size: 12px;">Customer GSTIN : #gstin_no#</span><br>
                    <!--   <span style="font-size: 12px;">Service Accounting Code :<br>
            Invoice Number : <br>
            Invoice Date : #show_policy_start_date#<br>
            Whether Tax is payable on Reverse Charge - No
            </span> -->
                </div>
                <div class="column" style="float: right;">
                    <b style="float: right;font-size:12px;"><span>For #show_company_name#</span>.</b><br>
                    <img src="http://motoraffinity.fynity.in/allience/quatepage/img/thanksimg/tatadigitalsigantune.png"
                        style="width: 35%; padding: 5px;float: right;">
                    <br><br><br>
                    <span style="float: right;font-size:11px;float: right;margin-top: -7px;">Duly Constituted
                        Attorney</span>
                </div>
            </div>
            <!--  <table>
          <tr>
              <td>
                   <b style="font-size: 12px;">Service Accounting Code :</b><br>
                  <b style="font-size: 12px;">POS Name : #show_pos_name#</b><br>
                  <b style="font-size: 12px;">POS Unique No. : #show_unique_no#</b><br>
                  <b style="font-size: 12px;">POS PAN Number. : #show_pan_no#</b><br>
                  <b style="font-size: 12px;">POS Email : #show_pos_email#</b><br><br>
              </td>
          </tr>
          </table> -->
            #show_pos_details#
            <b style="font-size: 12px;">Consolidated Stamp duty Paid vide GRAS GRN No. ************ dated
                #show_policy_start_date# **</b><br>
            <span style="font-size: 12px;">** Not Applicable for the State of Jammu & Kashmir</span><br><br>
            <span style="font-size: 12px;">In Witness whereof this Policy has been signed at MAHARASHTRA this
                #show_policy_start_date#.</span><br>
            <b class="italic-font">Disclaimer:</b>
            <p class="italic-font text-justify" style="font-size:11px;">In the event of misrepresentation,fraud or
                non-disclosure of material fact, the Company reserves the right to cancel the Policy. Please note that
                the insured vehicle was pre-inspected and a report was prepared accordingly. The existing damages to the
                vehicle as mentioned in the report shall not be paid by the Company. The policy is issued basis he
                information provided by you, which is available with the company. In case of discrepancy or non
                recording of relevant information in the policy, the insured is requested to bring the same to the
                notice of the company within 15 days.</p>
            <br>
            <b style="font-size: 12px;">IMPORTANT NOTICE :</b>
            <span class="text-justify" style="font-size:10px;">The Insured is not indemnified if the vehicle is used
                or driven otherwise than in accordance with this Schedule. Any payment made by the Company by reason of
                wider terms appearing in the Certificate in order to comply with the Motor Vehicle Act, 1988 is
                recoverable from the Insured. See the clause headed "AVOIDANCE OF CERTAIN TERMS AND RIGHT OF RECOVERY".
                For legal interpretation,English version will hold good.This document is to be read with the policy
                wordings terms and conditions governing the coverages which can be downloaded from our web site : <a
                    href="#show_ic_url#">#show_ic_url#</a></span> <br><br>
            <center><span style="text-align: center; font-size: 12px; margin-bottom: 100px;">Please call Toll Free No.
                    #show_ic_contact# for all Insurance Related Assistance</span></center>
            <br><br> <br><br> <br><br>
        </div>
    </div>
    </div>
    <br><br>
    <div id="wrapper1" style="background-color: #fff;">
        <center><img src="http://motoraffinity.fynity.in/allience/quatepage/img/thanksimg/0004.jpg"
                style="width: 100%;height: 800px; padding: 5px;"></center>
    </div>
    <br>
    <div style="background-color: #fff;" id="wrapper1">
        <span style="float: right;font-size: 12px;">Policy No: #show_policy_no# </span> <span
            style="font-size: 10px;">#created_at#</span><br><br><br>
        <div style="border:1px solid black !important; padding: 10px;">
            <p>(Please scan the following QR code with QR code reader enabled mobile to download and view the policy on
                your mobile)</p><br>
            <img src="https://chart.googleapis.com/chart?chs=240x240&cht=qr&chl=#prod_table#%2F&choe=UTF-8" title="" />
        </div>
    </div>
</body>

</html>
