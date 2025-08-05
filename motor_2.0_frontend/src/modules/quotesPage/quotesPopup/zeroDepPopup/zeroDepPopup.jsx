import React, { useState, useEffect } from "react";
import styled from "styled-components";
import PropTypes from "prop-types";
import { Row, Col, Form } from "react-bootstrap";
import { useForm } from "react-hook-form";

import Popup from "../../../../components/Popup/Popup";
import { useDispatch, useSelector } from "react-redux";
import "../idvPopup/IDVPopup";

import { setTempData } from "../../filterConatiner/quoteFilter.slice";

const ZeroDepPopup = ({ show, onClose, setZeroDep, zeroDep }) => {
	const dispatch = useDispatch();
	const { handleSubmit, register, watch, control, errors, setValue } = useForm({
		// resolver: yupResolver(),
		// mode: "all",
		// reValidateMode: "onBlur",
	});
	const { tempData } = useSelector((state) => state.quoteFilter);

	const onSubmit = (data) => {
		if (data === "NO") {
			setZeroDep(false);
			onClose(false);
		} else if (data === "YES") {
			setZeroDep(true);
			onClose(false);
		}

		//onClose(false);
	};

	const content = (
		<>
			<Conatiner>
				<Row>
					<PaymentTermTitle>Previous Zero Depth Details</PaymentTermTitle>
					<PopupSubTitle>
						Was Zero Depreciation a part of your previous policy?
					</PopupSubTitle>

					<div className="paymentTermRadioWrap zeroDepPop">
						<CloseButtonCpa onClick={() => onSubmit("NO")}>NO</CloseButtonCpa>
						<ApplyButton onClick={() => onSubmit("YES")}>YES</ApplyButton>
					</div>
				</Row>
			</Conatiner>
		</>
	);
	return (
		<Popup
			height={"auto"}
			width="50%"
			show={show}
			onClose={onClose}
			content={content}
			position="center"
			top="45%"
			outside={true}
			left="50%"
			hiddenClose={true}
			//noBlur="true"
		/>
	);
};

// PropTypes
ZeroDepPopup.propTypes = {
	show: PropTypes.bool,
	onClose: PropTypes.func,
};

// DefaultTypes
ZeroDepPopup.defaultProps = {
	show: false,
	onClose: () => {},
};

const Conatiner = styled.div`
	padding: 20px 50px;
	.zeroDepPop {
		width: 100%;
		margin-bottom: 20px !important;
		display: flex;
		justify-content: center;
	}
`;

const PaymentTermTitle = styled.div`
	float: left;
	width: 100%;
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
	font-size: 16px;
	line-height: 20px;
	color: #333;
	padding-bottom: 10px;
	border-bottom: solid 1px #e3e4e8;
`;

const PopupSubTitle = styled.div`
	float: left;
	width: 100%;
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
	font-size: 14px;
	line-height: 20px;
	color: #333;
	margin-top: 16px;
	margin-bottom: 16px;
`;

const ApplyButton = styled.button`
	width: 117px;
	height: 32px;
	border-radius: 4px;
	background-color: ${({ theme }) => theme.QuotePopups?.color || " #f3ff91"};
	border: ${({ theme }) => theme.QuotePopups?.border || "  solid 1px #bdd400"};
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
	font-size: 15px;
	line-height: 20px;
	color: #000;
	/* text-transform: uppercase; */
	margin: 0;
	float: right;
	border-radius: 50px;
	margin-right: 10px;
`;

const CloseButtonCpa = styled.button`
	width: 117px;
	height: 32px;
	border-radius: 4px;
	background-color: #a2a9ab;
	border: solid 1px #a2a9ab;
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
	font-size: 15px;
	line-height: 20px;
	color: #000;
	/* text-transform: uppercase; */
	margin: 0;
	float: right;
	border-radius: 50px;
	margin-right: 20px;
`;

export default ZeroDepPopup;
