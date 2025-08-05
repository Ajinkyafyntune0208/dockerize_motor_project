<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ config('app.name') }}</title>

	<style>
        @media only screen and (max-width: 480px) {
            .padding-class {
                padding: 3px 0px !important;
            }

            .ic-logo {
                height: 40px !important;
                width: 55px !important;
            }

            .margin-class{
                margin: 10px 20px !important;
            }
        }
	</style>
</head>

<body>
	<table align="center" border="0" cellpadding="0" cellspacing="0" style="background:#efeff4; padding:0; margin:0;" width="100%">
		<tbody>
			<tr>
				<td></td>
				<td align="center" style="padding:0;border-collapse:collapse" valign="top" width="600">
					<table align="center" border="0" cellpadding="0" cellspacing="0">
						<tbody>
							<tr>
								<td align="left" colspan="2" style="padding:0" valign="top">
									<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
										<tbody>
											<tr>
												<td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom" width="100%"><a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="{{ $mailData['logo'] }}" title="{{ config('app.name') }}" width="160" /> </a></td>
											</tr>
										</tbody>
									</table>
								</td>
							</tr>
							<tr>
								<td align="left" colspan="2" style="padding:0;background:#efeff4" valign="top">
									<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
										<tbody>
											<tr>
												<td align="left" style="background:#efeff4;padding:0" valign="top" width="10"></td>
												<td align="left" style="background:#ffffff;padding:0" valign="top" width="100%">
													<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
														<tbody>
															<tr>
																<td align="center" style="padding:0;font:normal 14px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;line-height:1.4em;background:#f9fafc" valign="top">
																	<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
																		<tbody>
																			<tr>
																				<td align="left" colspan="2"
																					style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
																					valign="top" class="padding-class">
																					<div style="margin: 20px 40px; letter-spacing: 0.3px;" class="margin-class">
																						@yield('content')
																						<div style="margin-top: 30px;">
																							<p style="font-size: 1rem;font-weight:bold">From,</p>
																							<a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img style="width:100%; height:12px" src="{{ asset("images/TMIBASL/email-logo.png") }}" title="{{ config('app.name') }}"/> </a>
																							{{-- <p style="font-size:0.7rem" align="left" >
																								AFL House, 1st Floor, Lok Bharti Complex, Marol Maroshi Road, Andheri East, Mumbai 400 059. India <br/>
																								Composite Broker License No. 375 I Validity 13-05-2023 to 12-05-2026 I CIN: U50300MH1997PLC149349 <br/>
																								A sister Company of TATA AIA Life Insurance Company Limited and TATA AIG General Insurance Company Limited <br/>
																								LifeKaPlan https://lifekaplan.com/  |  Web www.tatamotorsinsurancebrokers.com   |  <a href="https://www.linkedin.com/company/tata-motors-insurance-broking-and-advisory">LinkedIn</a> 
																							   </p>
																								   
																							   <p style="font-size:0.7rem;margin-top:30px" align="left">Insurance is the subject matter of solicitation. TMIBASL solicit policies offered by insurance companies.
																							   For more details on risk factors, terms, conditions, coverages and exclusions, please read the sales brochures carefully before concluding a sale.</p> --}}
																						</div>
		
																					</div>	
																			  	</td>
    																		</tr>
																			<tr>
																				<td style="background:#3D3691;; font-size:11px; color:#fff; text-align:center;">
																					<div style="width:90%; padding:5px 0 5px; margin:0 auto;">
																						<p style="font-size:0.7rem;" align="left">Insurance is the subject matter of solicitation. TMIBASL solicit policies offered by insurance companies.
																							For more details on risk factors, terms, conditions, coverages and exclusions, please read the sales brochures carefully before concluding a sale.</p>

																						<p style="font-size:0.7rem" align="left" >
																							AFL House, 1st Floor, Lok Bharti Complex, Marol Maroshi Road, Andheri East, Mumbai 400 059. India <br/>
																							Composite Broker License No. 375 I Validity 13-05-2023 to 12-05-2026 I CIN: U50300MH1997PLC149349 <br/>
																							A sister Company of TATA AIA Life Insurance Company Limited and TATA AIG General Insurance Company Limited <br/>
																							LifeKaPlan <a style="color:white" href="https://lifekaplan.com/">https://lifekaplan.com/</a> |  Web www.tatamotorsinsurancebrokers.com   |  <a style="color:white" href="https://www.linkedin.com/company/tata-motors-insurance-broking-and-advisory">LinkedIn</a> 
																						   </p>
																					</div>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
												<td align="left" style="background:#efeff4;padding:0" valign="top" width="10"></td>
											</tr>
										</tbody>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
				</td>
				<td></td>
			</tr>
		</tbody>
	</table>
</body>

</html>
