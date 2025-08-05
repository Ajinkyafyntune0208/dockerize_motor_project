<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ config('app.name') }} - Compare Car Insurance Policy</title>
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
												<td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom" width="100%"><a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="{{ $mailData['logo'] }}" title="{{ config('app.ame') }}" width="160" /> </a></td>
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
																						<p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear {{$mailData['name']}}</p>
																						<div style="padding: 0px 10px;">
																							<p style="margin-top: 2.5rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">You are just few steps away from securing your {{$mailData['product_name']}}</p>
																							<p style="font-size: 0.85rem;">Continue your proposal on {{ config('app.name') }} by clicking
																								<br>
																								<a href="{{ $mailData['link'] }}">link</a>
																							</p>
																							<!-- <p style="font-size: 0.8rem;margin: 35px 0px;">Kindly note that, this link will be valid till <strong> 11th August 2021 midnight 12 o'clock.</strong></p> -->
																							<p style="margin-top: 30px;font-size: 0.85rem;font-weight: bold;letter-spacing: 0.3px;">For any assistance, please feel free to connect with our customer care at our toll free number {{ config('constants.brokerConstant.tollfree_number')}} or drop an e-mail at <a href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a></p>
																							<div style="{{-- display: flex;flex-direction: column; --}}margin-top: 60px;">
																								<span>Yours Sincerely,</span><br><br>
																								<strong style="color: darkblue;">{{ config('app.name') }} Team</strong>
																							</div>
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
