<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ config('app.name') }} - Compare Vehicle Insurance Policy</title>
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
												<td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom" width="100%"><a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="{{-- https://www.comparepolicy.com/cp/welcome-emails/images/cp-logo.png --}}{{ $mailData['logo'] }}" title="{{ config('app.name') }}" width="160" /> </a></td>
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
																				<td align="left" colspan="2" style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px" valign="top">
																					<div style="margin: 40px 40px;">
																						<p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear {{ $mailData['name'] }},</p>
																						<p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;"> Your Inspection request with
																						    {{ $mailData['insurer'] }} for vehicle reg no. is approved. Kindly click on the link
																						    {{ $mailData['payment_url'] }} for the payment of Rs. {{ $mailData['payment_amount'] }}.
																						</p>
																						<p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">Be Wise. Stay Insured.</p>
																					</div>
																				</td>
																			</tr>
																			<tr>
																				<td style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
																					<div style="width:80%; padding:10px 0 10px; margin:0 auto;">
																						<!-- CIN : U74140DL2015PTC276540 Compare Policy Insurance Web
																						Aggregators Pvt Ltd. IRDAI Web Aggregator Registration
																						No. 010, License Code No IRDAI/WBA23/15 -->
																						{{ config('constants.brokerConstant.code') }}
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
