import styled from "styled-components";
import './input.scss'

/*
'Form Group' has 4 different props-(sm,md,lg,xlg)
'Label' has 2 different props-(md,lg)
'TextInput' has 2 different props-(md,lg)
'Form' and 'FormWrapper' has no props
*/
const tablet = "768px";
const colorgrey = "#777777";
const colorsecondary = "#006600";
const danger = "#bf1650";
export const FormWrapper = styled.div`
	display: flex;
	justify-content: space-between;
	flex-wrap: wrap;
	width: 100%;
	@media (max-width: ${tablet}) {
		justify-content: flex-end;
		width: 97%;
	}
`;

export const FormGroup = styled.div`
	position: relative;
	text-align: ${(props) => {
		if (props.xlg) return "center";
		else return "left";
	}};
	font-family: ${({theme}) => theme?.regularFont?.fontFamily ? theme?.regularFont?.fontFamily : 'basier_squareregular'};
	width: ${(props) => {
		if (props.lg) return "69%";
		else if (props.md) return "48.3%";
		else if (props.sm) return "30%";
		else if (props.xlg) return "100%";
	}};
	min-height: ${(props) => {
		if (props.sm) return "3.75rem";
		else if (props.lg) return "4.5rem";
		else if (props.md) return "90px";
		else if (props.xs) return "5rem";
		else return "0";
	}};
	@media (max-width: ${tablet}) {
		width: 100%;
		min-height: 76px;
	}
`;

export const Label = styled.label`
	position: absolute;
	font-family: ${({theme}) => theme?.regularFont?.fontFamily ? theme?.regularFont?.fontFamily : 'basier_squareregular'};
	color: ${(props) => {
		if (props.editMode) {
			return "#107591";
		} else if (props.lg) {
			return "#666666";
		} else return colorgrey;
	}};

	transition: all 0.3s;
	cursor: text;
	top: ${(props) => {
		if (props.lg) return "14px";
		if (props.md) return "18px";
	}};
	left: ${(props) => {
		if (props.lg) return "20px";
		if (props.md) return "14px";
	}};
	font-weight: ${(props) => {
		if (props.lg) return "500";
		else if (props.md) return "500";
	}};
	font-size: ${(props) => {
		if (props.lg) return "18px";
		else if (props.md) return "0.9rem";
	}};

	& > span {
		font-size: 1.5rem;
	}
`;

// max-width: ${({ other }) => (!other ? 'inherit' : '234.03px')};
export const TextInput = styled.input`
  text-align:${(props) => (props.noPadding ? "center" : "")};
  box-shadow: none;
  width: 100%;
  min-width: ${(props) => (props.noPadding ? "" : "180px")};
  border-radius: ${({ xs }) => (xs ? 0 : "4px")}};
  color: rgba(0, 0, 0, 0.6);
  text-transform:${(props) => props.capital && "capitalize"};
  padding: ${(props) => {
			if (props.lg && !props.noPadding) return "15px 15px 0 18px";
			if (props.md) return "10px 10px 0 8px";
		}};
  font-weight: 400;
  font-size: ${(props) => {
			if (props.lg) return "18px";
			if (props.md) return "15px";
		}};
  border: ${(props) => {
			if (props.lg && props.error) return "2px";
			else if (props.lg) return "1px";
			else if (props.xs) return "2px";
			else return "1px";
		}}
    solid
    ${(props) => {
					if (props.error) return danger;
					if (props.xs) return "#107591";
					else return "rgb(210, 211, 212)";
				}};
    height: ${(props) => {
					if (props.lg) return "3.75rem";
					if (props.md) return "2.8rem";
				}};
  &:focus {
    outline: none;
    border: ${(props) => (props.error ? "2px" : "2px")} solid
      ${(props) => (props.error ? danger : colorsecondary)};
    & ~ label {
      font-size: 11px;
      top: 8px;
      left: 20px;
      font-weight: 400;
      font-family:${({theme}) => theme?.mediumFont?.fontFamily ? theme?.mediumFont?.fontFamily : 'basier_squaremedium'};
      color: ${colorsecondary};
      & span {
        font-size: 9px;
        font-weight: 800;
      }
    }
  }
  &:not(:placeholder-shown) ~ label {
    font-size: 11px;
    font-family:${({theme}) => theme?.mediumFont?.fontFamily ? theme?.mediumFont?.fontFamily : 'basier_squaremedium'};
    top: 8px;
    left: 20px;
    font-weight: 400;
    color: ${(props) =>
					props?.placeholderColor
						? props?.placeholderColor
						: colorsecondary} !important;
    & span {
      font-size: 9px;
      font-weight: 800;
    }
  }
  @media (max-width: 767px) {
    min-width: ${(props) => (props.noPadding ? "" : "353px")};
    max-width: ${(props) => (props.noPadding ? "" : "inherit")};
  }
  @media (max-width: 450px) {
    min-width: ${(props) => (props.noPadding ? "" : "195px")};
    max-width: ${(props) => (props.noPadding ? "" : "inherit")};
  }
`;

export const RadioContainer = styled.div`
	display: flex;
	flex-wrap: wrap;
	align-content: space-between;
	justify-content: ${(props) => (props.md ? "space-between" : "space-evenly")};
	width: ${(props) => {
		if (props.md) return "47%";
		if (props.lg) return "100%";
	}};
	@media (max-width: ${tablet}) {
		width: ${(props) => (props.lg ? "25%" : "98%")};
		text-align: center;
		margin: 0 auto 4.5rem;
	}
`;

export const RadioLabel = styled.label`
	font-size: 1.8rem;
	min-width: 180px;
	:110px ;
	border-radius: 4px;
	text-align: center;
	border: 1px solid
		${(props) => (props.error === "danger" ? danger : "rgb(210, 211, 212)")};
	color: ${colorgrey};
	cursor: pointer;
	transition: all 0.5s;
	height: ${(props) => {
		if (props.sm) return "6rem";
		if (props.lg) return "20rem";
	}};
	display: ${(props) => props.lg && "flex"};
	flex-direction: ${(props) => props.lg && "column"};
	align-items: ${(props) => props.lg && "center"};
	justify-content: ${(props) => props.lg && "space-around"};
	width: ${(props) => {
		if (props.sm) return "45%";
		if (props.lg) return "25%";
	}};
	padding: ${(props) => {
		if (props.sm) return "1.5rem 1.5rem";
		if (props.lg) return "2rem 1.5rem 1rem 1rem";
	}};
	font-size: ${(props) => {
		if (props.lg) return "2rem";
	}};
	& svg {
		height: 5rem;
		fill: ${colorsecondary};
	}
	@media (max-width: 768px) {
		width: ${(props) => props.lg && "100%"};
		&:not(:last-child) {
			margin-bottom: ${(props) => props.lg && "10px"};
		}
	}
`;
export const RadioButton = styled.span`
	display: inline-block;
	height: 20px;
	width: 20px;
	border: 2px solid #cccccc;
	border-radius: 50%;
	transform: translateY(3px);
	position: relative;
	margin-right: ${(props) => {
		if (props.marginsm) return "1rem";
	}};
	&::after {
		content: "";
		position: absolute;
		left: 50%;
		top: 50%;
		transform: translate(-50%, -50%);
		height: 20px;
		width: 20px;
		position: absolute;
		background-color: ${colorsecondary};
		border-radius: 50%;
		opacity: 0;
		transition: opacity 0.1s;
	}
`;

export const Radio = styled.input.attrs({
	type: "radio",
})`
	display: none;
	&:checked + label {
		border: 2px solid ${colorsecondary};
		color: white;
		background-color: ${colorsecondary};
	}
	&:checked + label svg {
		fill: white;
	}
	&:checked + label span::after {
		opacity: 1;
		border: 4px solid white;
		outline: none;
	}
`;

export const AgeWrapper = styled.div`
	position: absolute;
	background-color: #ececec;
	text-align: center;
	top: 5.7rem;
	right: 5px;
	width: 8.5rem;
	height: 3.5rem;
	font-family: ${({theme}) => theme?.mediumFont?.fontFamily ? theme?.mediumFont?.fontFamily : 'basier_squaremedium'};;
	padding: 7px 0;
	font-size: 14px;
	transform: translate(-1rem, -4.5rem);
	border-radius: 4px;
	color: rgba(0, 0, 0, 0.6) !important;
`;
export const DropDownWrapper = styled.div`
	width: 50%;
	margin: 6rem auto 0;
`;

export const ErrorMsg = styled.span`
	display: block;
	margin-top: 4px;
	line-height: 17px;
	font-size: ${(props) => props.fontSize || "14px"};
	color: #d43d3d;
	font-family: "${({theme}) => theme?.regularFont?.fontFamily ? theme?.regularFont?.fontFamily : 'basier_squareregular'}";
`;

export const BackButton = styled.button`
	margin: ${({ BlockLayout }) => BlockLayout ? `-123px 60px 20px 30px` : `-40px 60px 20px 30px`} !important;
	outline: none !important;
	border: none !important;
	color: black;
	@media (max-width: 992px) {
		margin: ${({ BlockLayout }) => BlockLayout ? `-123px 60px 20px 30px` : `-40px 60px 20px 30px`} !important;
	@media (max-width: 767px) {
		margin: 0px 60px 20px 60px !important;
	}
	@media (max-width: 600px) {
		margin: 0px 0 20px 30px !important;
	}
`;
