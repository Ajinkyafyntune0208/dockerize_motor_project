import Popup from "components/Popup/Popup";
import { saveQuoteData, set_temp_data } from "modules/Home/home.slice";
import React from "react";
import { useDispatch, useSelector } from "react-redux";
import { useHistory } from "react-router";
import styled from "styled-components";
import { setTempData } from "../filterConatiner/quoteFilter.slice";
import {
	SaveAddonsData,
	setQuotesList,
	clear as clearQuote,
} from "../quote.slice";
import { useMediaPredicate } from "react-media-hook";
const AbiblPopup = ({
	type,
	typeId,
	token,
	enquiryId,
	show,
	setShow,
	editPopUp,
	setEditPopUp,
	setToasterShown,
}) => {
	const onClose = () => {
		setToasterShown(false);
		setShow(false);
	};
	const { temp_data } = useSelector((state) => state.home);
	const lessHeight = useMediaPredicate("(max-height: 700px)");
	const lessthan767 = useMediaPredicate("(max-width: 767px)");

	const history = useHistory();

	const dispatch = useDispatch();

	const handleEdit = () => {
		setToasterShown(false);
		setEditPopUp(true);
		setShow(false);
	};

	const content = (
		<TopDiv>
			{/* <button onClick={onClose}>Close</button> */}
			<div
				className="text-center"
				style={{ position: "absolute", top: "0", left: "50%" }}
			>
				<i className="fa fa-chevron-up" style={{ color: "white" }}></i>
			</div>
			{/* <div style={{ display: 'flex', width: '100%', padding: '25px 0px 10px 25px' }}>
             <div style={{ width: '35%' }}>
               <div>Car Ownership<i className="fa fa-exclamation-circle mt-2" style={{ marginLeft: '10px' }}></i></div>
                <div className="text_size">Individual <span className="edit">Edit</span></div>
                <div className="mt-2">Policy Tenure <i className="fa fa-exclamation-circle" style={{ marginLeft: '10px' }}></i></div>
                <div className="text_size">Comprehensive</div>
             </div>
            <div style={{ width: '65%' }}>
                <div className="text_size">Has your current policy expired?<strong style={{ borderBottom: '2px solid black', marginLeft: '10px' }}>No</strong><span className="edit">Edit</span></div>
                <div className="text_size" style={{ padding: '10px 0px' }}>Have you made a claim in the previous year?<strong style={{ borderBottom: '2px solid black', marginLeft: '10px' }}>No</strong></div>
                <div className="text_size">Your Previous NCB(No Claim Bonus) <strong style={{ borderBottom: '2px solid black', marginLeft: '10px' }}>35%</strong><i className="fa fa-exclamation-circle" style={{ marginLeft: '10px' }}></i></div>
            </div>
            </div> */}
			<div
				style={{
					width: "100%",
					padding: "25px 0px 5px 25px",
					textAlign: "center",
					color: "#fff",
				}}
			>
				<p>
					{/* <strong>Car Details:</strong> {temp_data?.manfName}-
					{temp_data?.modelName}-{temp_data?.versionName} */}
					Please verify you details here.
				</p>
			</div>
			<div className="text-center" style={{ padding: "0px 0px 10px 0px" }}>
				<button
					onClick={onClose}
					style={{
						background: "none",
						color: "#fff",
						borderRadius: "5px",
						border: "1px solid #fff",
						marginRight: "5px",
					}}
				>
					Skip
				</button>
				<button
					onClick={handleEdit}
					style={{
						background: "none",
						color: "#fff",
						borderRadius: "5px",
						border: "1px solid #fff",
						marginLeft: "5px",
					}}
				>
					Edit
				</button>
			</div>
		</TopDiv>
	);

	return (
		<Popup
			height="auto"
			width="400px"
			content={content}
			show={show}
			// position="middle"
			position={lessthan767 ? "responsiveTop" : "middle"}
			left={lessthan767 ? "50%" : "50%"}
			// position="center"
			hiddenClose
			backGround="transparent"
		/>
	);
};

export default AbiblPopup;

const TopDiv = styled.div`
	.edit {
		margin-left: 25px;
		font-size: 0.9rem;
		color: red;
	}
	.text_size {
		font-size: 0.9rem;
	}
`;
