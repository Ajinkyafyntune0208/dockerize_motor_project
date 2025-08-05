import React from "react";
import styled from "styled-components";
import PropTypes from "prop-types";
import { Row } from "react-bootstrap";
import { useForm } from "react-hook-form";

import Popup from "../../../../components/Popup/Popup";
import { useDispatch, useSelector } from "react-redux";
import "../idvPopup/IDVPopup";

import { setTempData } from "../../filterConatiner/quoteFilter.slice";

const CpaPopup = ({ show, onClose, setCpa, cpa }) => {
	const dispatch = useDispatch();
	const { register, watch } = useForm({
		// resolver: yupResolver(),
		// mode: "all",
		// reValidateMode: "onBlur",
	});
	const { tempData } = useSelector((state) => state.quoteFilter);

	const selectedReason = watch("noCpaReason");
	const onSubmit = (data) => {
		if (data === "confirm" && selectedReason) {
			setCpa(false);
			onClose(false);
			dispatch(
				setTempData({
					cpaReason: selectedReason,
				})
			);
		} else if (data === "close") {
			setCpa(true);
			onClose(false);
			dispatch(
				setTempData({
					cpaReason: false,
				})
			);
		}

		//onClose(false);
	};

	const content = (
		<>
			<Conatiner>
				<Row>
					<PaymentTermTitle>
						CPA (Rs. 15 lac cover mandated by IRDAI)
					</PaymentTermTitle>
					<PopupSubTitle>
						You have not selected this mandatory Personal Accident cover for the
						owner of the vehicle with sum assured of INR 15 Lac. Please go back
						to select PA Owner Driver cover from the add-on section. In case you
						are opting out of this add-on, please select one of the reasons:
					</PopupSubTitle>

					<div className="paymentTermRadioWrap">
						<label className="panel-heading ratioButton IDVRatio">
							{/* radioCheckedColor className */}
							<input
								type="radio"
								className="idvInputclassName"
								ref={register}
								name="noCpaReason"
								defaultChecked={
									tempData?.noCpaReason ===
									"I have another motor policy with PA owner driver cover in my name"
										? true
										: false
								}
								value="I have another motor policy with PA owner driver cover in my name"
							/>
							<span className="checkmark"></span>
							<span
								className={`checkBoxTextIdv ${
									selectedReason ===
									`I have another motor policy with PA owner driver cover in my name`
										? "txtCheckedBold"
										: ""
								}`}
							>
								I have another motor policy with PA owner driver cover in my
								name
							</span>
						</label>
					</div>
					<div className="paymentTermRadioWrap">
						<label className="panel-heading ratioButton IDVRatio">
							<input
								type="radio"
								className="idvInputClass"
								name="noCpaReason"
								ref={register}
								value="I have another PA policy with cover amount greater than INR 15 Lacs"
								defaultChecked={
									tempData?.noCpaReason ===
									"I have another PA policy with cover amount greater than INR 15 Lacs"
										? true
										: false
								}
							/>
							<span className="checkmark"></span>
							<span
								className={`checkBoxTextIdv ${
									selectedReason ===
									`I have another PA policy with cover amount greater than INR 15 Lacs`
										? "txtCheckedBold"
										: ""
								}`}
							>
								I have another PA policy with cover amount greater than INR 15
								Lacs
							</span>
						</label>
					</div>
					<div className="paymentTermRadioWrap">
						<label className="panel-heading ratioButton IDVRatio">
							<input
								type="radio"
								className="idvInputClass"
								name="noCpaReason"
								ref={register}
								value="I do not have a valid driving license."
								defaultChecked={
									tempData?.noCpaReason ===
									"I do not have a valid driving license."
										? true
										: false
								}
							/>
							<span className="checkmark"></span>
							<span
								className={`checkBoxTextIdv ${
									selectedReason === `I do not have a valid driving license.`
										? "txtCheckedBold"
										: ""
								}`}
							>
								I do not have a valid driving license.
							</span>
						</label>
					</div>

					<div className="paymentTermRadioWrap">
						<ApplyButton onClick={() => onSubmit("confirm")}>
							Confirm
						</ApplyButton>
						<CloseButtonCpa onClick={() => onSubmit("close")}>
							Close
						</CloseButtonCpa>
					</div>
				</Row>
			</Conatiner>
		</>
	);
	return (
		<Popup
			height={"auto"}
			width="60%"
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
CpaPopup.propTypes = {
	show: PropTypes.bool,
	onClose: PropTypes.func,
};

// DefaultTypes
CpaPopup.defaultProps = {
	show: false,
	onClose: () => {},
};

const Conatiner = styled.div`
	padding: 20px 50px;
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

const PopupSubHead = styled.div`
	float: left;
	width: 100%;
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
	font-size: 14px;
	line-height: 17px;
	color: #333;
	margin-bottom: 12px;
`;

const ApplyButton = styled.button`
	width: 117px;
	height: 32px;
	border-radius: 4px;
	background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
	border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
	font-family: ${({ theme }) =>
		theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
	font-size: 15px;
	line-height: 20px;
	color: #000;
	/* text-transform: uppercase; */
	margin: 0;
	float: right;
	border-radius: 50px;
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

export default CpaPopup;
