import styled from "styled-components";
import { Row } from "react-bootstrap";

export const CustomRow = styled(Row)`
  display: flex;
  padding: 0;
  margin: 10px 0 0;
  justify-content: center;
  flex-wrap: nowrap;
  @media (max-width: 991px) {
    flex-wrap: wrap;
  }
`;

export const ButtonContainer = styled.div`
  display: flex;
  margin-top: 1rem;
  @media (max-width: 768px) {
    margin-top: 15px;
  }
  justify-content: center;
  & > button {
    // width: 230px !important;
    width: 320px !important;
    background-color: #f2581b !important;
    &:hover,
    &:focus {
      background-color: #ff4d07 !important;
    }
  }
  & > button > i {
    padding-left: 15px;
    transition: transform 0.5s;
  }
  & > button:hover,
  & > button:focus,
  & > button:active {
    & > i {
      transform: translateX(10px);
    }
  }
  @media (max-width: 768px) {
    & > button {
      width: 95% !important;
    }
  }
`;

export const Term = styled.a`
  &:link,
  &:visited {
    // text-decoration: none;
    // color: #666;
    color: #107591;
    text-decoration: underline;
  }
  color: #107591;
`;

export const Title = styled.p`
  text-align: center;
  font-size: 34px;
  font-weight: 500;
  color: #000;
  font-family: ${({ theme }) =>
    theme?.mediumFont?.fontFamily
      ? theme?.mediumFont?.fontFamily
      : "basier_squaremedium"};
  margin: 0;
  @media (min-width: 768px) and (max-width: 991px) {
    font-size: 28px;
    margin-top: 20px;
  }
  @media (max-width: 768px) {
    margin-top: 8px;
    font-size: 28px;
  }
`;

export const Description = styled.p`
  font-size: 20px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"Inter-Light"`};
  font-weight: 400;
  color: #333;
  line-height: 28px;
  display: inline-block;
  text-align: center;
  margin-bottom: 22px;
  @media (max-width: 768px) {
    font-size: 16px;
    width: 100%;
    margin-bottom: 40px;
  }
`;

export const ConfirmButton = styled.button`
  background: #ffc000;
  color: #fff;
  border-radius: 5px;
  padding: 0 30px;
  display: inline-block;
  width: 150px;
  // font-size: 20px;
  font-size: 17px;
  text-align: center;
  height: 55px;
  border: none;
  outline: none;
  &:focus {
    outline: none;
  }
`;

export const Agree = styled.div`
  text-align: center;
  padding-top: 7px;
  font-size: 13px;
  margin-top: 6px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};

  color: #666;
`;

export const CheckboxWrapper = styled.div`
  margin: 0 auto;
  position: relative;
  float: left;
  width: 100%;
  margin-bottom: 32px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basiersquare_regular_italic"`};
  font-size: 16px;
  line-height: 24px;
  color: rgba(0, 0, 0, 0.6);
  margin-top: 10px;
  div {
    float: left;
    margin-left: 28px;
  }
  label {
    width: 18px;
    height: 18px;
    cursor: not-allowed;
    position: absolute;
    top: 3px;
    left: 0;
    background: #107591;
  }
  label:after {
    content: "";
    width: 12px;
    height: 7px;
    position: absolute;
    top: 4px;
    left: 3px;
    border: 2px solid #fcfff4;
    border-top: none;
    border-right: none;
    background: transparent;
    opacity: 0;
    -webkit-transform: rotate(-45deg);
    transform: rotate(-45deg);
  }
  label:hover::after {
    opacity: 0.3;
  }
  input[type="checkbox"] {
    visibility: hidden;
    width: 20px;
    margin: 0;
  }
  input[type="checkbox"]:checked + label:after {
    opacity: 1;
  }
  @media only screen and (min-width: 768px) and (max-width: 991px) {
    display: flex;
    margin-bottom: 38px;
    div {
      margin-left: 11px;
    }
  }
  @media only screen and (max-width: 767px) {
    display: flex;
    font-size: 12px;
    margin-bottom: 13px;
    right: 0px !important;
    width: 100%;
    line-height: 16px;
    div {
      margin-left: 11px;
    }
  }
  @media (max-width: 480px) {
    display: flex;
    div {
      margin-left: 13px;
      width: 80%;
    }
    svg {
      height: 24px;
      width: 24px;
    }
  }
`;

export const Container = styled.div`
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: top;
  /* IE10 Consumer Preview */
  background-image: ${({ theme }) =>
    !theme?.Home?.backgroundColor
      ? ""
      : `-ms-linear-gradient(bottom, #fff 70%, ${theme?.Home?.backgroundColor} 100%)`};
  background-image: ${({ theme }) =>
    !theme?.Home?.backgroundColor
      ? ""
      : `-moz-linear-gradient(bottom, #fff 70%, ${theme?.Home?.backgroundColor} 100%)`};
  background-image: ${({ theme }) =>
    !theme?.Home?.backgroundColor
      ? ""
      : `-o-linear-gradient(bottom, #fff 70%, ${theme?.Home?.backgroundColor} 100%)`};
  background-image: ${({ theme }) =>
    !theme?.Home?.backgroundColor
      ? ""
      : `-webkit-gradient(linear, left bottom, left bottom, color-stop(0, #fff), color-stop(1, ${theme?.Home?.backgroundColor}))`};
  background-image: ${({ theme }) =>
    !theme?.Home?.backgroundColor
      ? ""
      : `-webkit-linear-gradient(bottom, #fff 70%, ${theme?.Home?.backgroundColor} 100%)`};
  height: 100%;
`;

export const FormContainer = styled.div`
  margin: 0 0 0;
  width: ${({ width }) => width || "690px"};
  @media (max-width: 991px) {
    width: ${({ width }) =>
      width === "1007.990px" ? "750px" : width || "690px"};
    margin: 0 0px 0px;
  }
  @media (max-width: 767px) {
    width: 100%;
    margin: 0 15px 50px;
  }
  @media (max-width: 450px) {
    margin: 0 15px 50px;
  }
`;

export const Avatar = styled.img`
  width: 85px;
  height: 85px;
  border: ${({ theme }) => theme?.avatar?.border || "solid 2px #bdd400"};
  border-radius: 50%;
  box-shadow: 0 3px 6px 0 rgba(0, 0, 0, 0.2);
  padding : ${({type}) => type === "cv" ? "8px" : "0px"};
  background: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "TATA"
      ? "linear-gradient(to right, #00bcd4 0%, #ae15d4 100%)"
      : theme.Stepper?.stepperColor?.background || "#bdd400"};
  @media (max-width: 767px) {
  }
`;

export const DatePickerDropDown = styled.select`
  border: none;
  font-size: 14px;
  width: 80px;
`;

export const DateWrapper = styled.span`
  .react-datepicker__header {
    background-color: #ffffff;
    border-bottom: 0;
  }
  .react-datepicker__day--today {
    font-weight: inherit;
  }
  .react-datepicker__day--keyboard-selected,
  .react-datepicker__month-text--keyboard-selected,
  .react-datepicker__quarter-text--keyboard-selected,
  .react-datepicker__year-text--keyboard-selected {
    background-color: #98bbfd;
  }
  .react-datepicker__day--selected,
  .react-datepicker__day--in-selecting-range,
  .react-datepicker__day--in-range,
  .react-datepicker__month-text--selected,
  .react-datepicker__month-text--in-selecting-range,
  .react-datepicker__month-text--in-range,
  .react-datepicker__quarter-text--selected,
  .react-datepicker__quarter-text--in-selecting-range,
  .react-datepicker__quarter-text--in-range,
  .react-datepicker__year-text--selected,
  .react-datepicker__year-text--in-selecting-range,
  .react-datepicker__year-text--in-range {
    background-color: #98bbfd;
    color: #000;
    outline: none;
    border-radius: 0;
  }
  .react-datepicker__day--outside-month {
    visibility: hidden;
    color: transparent;
    pointer-events: none;
  }
  .react-datepicker__day,
  .react-datepicker__day-name {
    padding: 0.09rem;
    margin: 0;
    width: 2.3rem;
  }
  .react-datepicker__day:hover {
    border-radius: 0;
    background-color: #eaeaea;
  }
  .react-datepicker__day--disabled:hover {
    background-color: transparent;
  }
  .react-datepicker__input-container input {
    border: 1px solid #ced4da;
  }
  @-moz-document url-prefix() {
    .date {
      border: 1px solid #ced4da;
      z-index: 0;
    }
  }
  @media screen and (-ms-high-contrast: active), (-ms-high-contrast: none) {
    .date {
      border: 1px solid #ced4da;
      padding: 7px !important;
      z-index: 0;
    }
  }
`;

export const CustomWrap = styled.div`
  margin: 0 auto;
  width: 100%;
  .travelingCheckDiv {
    float: left;
    width: 100%;
    margin-bottom: 24px;
    padding: 0 15px;
  }
  .multiTripTxt {
    float: left;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basiersquare_regular_italic"`};
    font-size: 16px;
    line-height: 24px;
    color: rgba(0, 0, 0, 0.6);
  }
  .multiTripCheckboxWrap {
    float: right;
  }
  .multitripCheckLabel {
    float: left;
    font-size: 20px;
    line-height: 24px;
    color: rgba(0, 0, 0, 0.6);
    margin: 4px 8px;
  }

  .tgl {
    display: none;
  }
  .tgl,
  .tgl:after,
  .tgl:before,
  .tgl *,
  .tgl *:after,
  .tgl *:before,
  .tgl + .tgl-btn {
    box-sizing: border-box;
  }
  .tgl::-moz-selection,
  .tgl:after::-moz-selection,
  .tgl:before::-moz-selection,
  .tgl *::-moz-selection,
  .tgl *:after::-moz-selection,
  .tgl *:before::-moz-selection,
  .tgl + .tgl-btn::-moz-selection {
    background: none;
  }
  .tgl::selection,
  .tgl:after::selection,
  .tgl:before::selection,
  .tgl *::selection,
  .tgl *:after::selection,
  .tgl *:before::selection,
  .tgl + .tgl-btn::selection {
    background: none;
  }
  .tgl + .tgl-btn {
    float: left;
    outline: 0;
    display: block;
    width: 54px;
    height: 31px;
    position: relative;
    cursor: pointer;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
  }
  .tgl + .tgl-btn:after,
  .tgl + .tgl-btn:before {
    position: relative;
    display: block;
    content: "";
    width: 50%;
    height: 100%;
  }
  .tgl + .tgl-btn:after {
    left: 0;
  }
  .tgl + .tgl-btn:before {
    display: none;
  }
  .tgl:checked + .tgl-btn:after {
    left: 50%;
  }
  .tgl-ios + .tgl-btn {
    background: #d2d3d4;
    border-radius: 2em;
    padding: 2px;
    -webkit-transition: all 0.4s ease;
    transition: all 0.4s ease;
    margin-bottom: 0;
  }
  .tgl-ios + .tgl-btn:after {
    border-radius: 2em;
    background: #fff;
    -webkit-transition: left 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275),
      padding 0.3s ease, margin 0.3s ease;
    transition: left 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275),
      padding 0.3s ease, margin 0.3s ease;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1), 0 0 0 rgba(0, 0, 0, 0.08);
  }
  .tgl-ios + .tgl-btn:hover:after {
    will-change: padding;
  }
  .tgl-ios + .tgl-btn:active {
    box-shadow: inset 0 0 0 2em #d2d3d4;
  }
  .tgl-ios + .tgl-btn:active:after {
    padding-right: 0.8em;
  }
  .tgl-ios:checked + .tgl-btn {
    background: #107591;
  }
  .tgl-ios:checked + .tgl-btn:active {
    box-shadow: none;
  }
  .tgl-ios:checked + .tgl-btn:active:after {
    margin-left: -0.8em;
  }

  @media only screen and (max-width: 767px) {
    .travelingCheckDiv {
      margin-bottom: 10px;
    }
    .multiTripTxt {
      width: 208px;
      font-size: 12px;
      line-height: 18px;
    }
    .multitripCheckLabel {
      font-size: 16px;
    }
  }
`;

export const MultiTripDays = styled.div`
  display: flex !important;
  flex-wrap: wrap !important;
  justify-content: center !important;

  .multiTripLabel {
    font-size: 20px;
    line-height: 25px;
    color: #666666;
    margin-bottom: 8px;
  }

  .selectIncome {
    position: relative;
    width: 320px;
    height: 60px;
    border-radius: 4px;
    margin: 26px auto 60px;
  }
  .selectIncome .bootstrap-select {
    text-align: center;
    width: 320px !important;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squarebold"`};
    margin: 0 auto;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
  }
  .selectIncome .bs-caret {
    display: none;
  }
  .selectIncome .bootstrap-select .dropdown-menu li a {
    padding: 15px 20px;
    font-size: 18px;
    color: rgba(0, 0, 0, 0.6);
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  }
  .bs-actionsbox,
  .bs-donebutton,
  .bs-searchbox {
    padding: 4px 6px;
  }
  .selectIncome > .btn-default.active,
  .btn-default:active,
  .selectIncome > .open > .dropdown-toggle.btn-default {
    background-color: #fff;
  }
  .selectIncome > .bootstrap-select .dropdown-toggle:focus {
    outline: none !important;
  }
  .dropup .dropdown-menu,
  .navbar-fixed-bottom .dropdown .dropdown-menu {
    top: 56px;
  }
  .dropdown .dropdown-menu > .active > a {
    background-color: #107591;
    color: #fff !important;
  }
  .selectIncome > .open > .dropdown-toggle.btn-default {
    -webkit-appearance: none;
    color: rgba(0, 0, 0, 0.6);
    border: solid 2px #107591;
  }
  .selectIncome .bootstrap-select .dropdown-menu.inner {
    max-height: 156px;
  }

  .selectIncome > .bootstrap-select > .dropdown-toggle {
    width: 320px;
    padding: 5px 20px;
    font-size: 18px;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
    line-height: 1;
    border: solid 1px #d2d3d4;
    border-radius: 5px;
    background-color: #fff;
    height: 60px;
    -webkit-appearance: none;
    color: rgba(0, 0, 0, 0.6);
  }
  .selectIncome > .bootstrap-select > .dropdown-toggle:hover {
    -webkit-appearance: none;
    background-size: 20px;
  }

  .selectIncome > .form-control:active,
  .selectIncome > .form-control:focus {
    height: 44px;
    border-radius: 4px;
    border: 1px solid #ccc;
    box-shadow: none;
  }
  .selectIncome .bootstrap-select .dropdown-menu.open {
    border: solid 2px #107591;
    max-height: 217px !important;
    min-height: 216px !important;
    top: 56px;
  }

  .selectTripDays {
    display: none;
  }

  .selectpicker {
    height: 60px !important;
    -webkit-appearance: none;
    -moz-appearance: none;
    text-indent: 1px;
    text-overflow: "";
  }

  .multiTripOption {
    float: left;
    width: auto;
    margin-bottom: 22px;
  }

  .dropArrow {
    display: block;
    background: url(${import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""}/assets/images/arrow_selectBox.png)
      no-repeat;
    background-position: 0;
    background-size: 24px;
    position: absolute;
    right: 16px;
    top: 22px;
    width: 24px;
    height: 18px;
    pointer-events: none;
  }

  .multiTripOption group {
    width: 100%;
  }
  .multiTripOption input[type="radio"] {
    opacity: 0;
    width: 100%;
    height: 60px;
    background-color: blue;
    position: relative;
    margin: 0;
    cursor: pointer;
  }
  .multiTripOption .input-container {
    float: left;
    height: 60px;
    width: 138px;
    line-height: 42px;
    text-align: center;
    position: relative;
  }
  .multiTripOption .input-container:first-child label {
    border-radius: 5px 0 0 5px;
    border-left: solid 1px #d2d3d4;
  }
  .multiTripOption .input-container:last-child label {
    border-radius: 0 5px 5px 0;
    border-right: 1px solid #ccc;
  }
  .multiTripOption label {
    width: 100%;
    height: 100%;
    position: absolute;
    border-top: solid 1px #d2d3d4;
    border-right: solid 1px #d2d3d4;
    border-bottom: solid 1px #d2d3d4;
    border-left: none;
    background-color: #ffffff;
    top: 0;
    left: 0;
    font-size: 20px;
    line-height: 25px;
    color: rgba(0, 0, 0, 0.4);
    font-weight: 500;
    padding: 15px;
    cursor: pointer;
  }
  .multiTripOption label:hover {
    background-color: #3091ac;
    color: white;
  }

  .multiTripOption input:checked + label {
    top: 0;
    left: 0;
    border: none;
    box-shadow: 0 3px 6px 0 rgba(0, 0, 0, 0.16);
    background-color: #107591;
    color: white;
  }

  .multiTripOption .input-container {
    float: left;
    height: 60px;
    width: 138px;
    line-height: 42px;
    text-align: center;
    position: relative;
  }
  .multiTripOption .input-container:first-child label {
    border-radius: 5px 0 0 5px;
    border-left: solid 1px #d2d3d4;
  }
  .multiTripOption .input-container:last-child label {
    border-radius: 0 5px 5px 0;
    border-right: 1px solid #ccc;
  }

  @media only screen and (max-width: 767px) {
    .multiTripLabel {
      font-size: 14px;
      line-height: 18px;
    }
    .selectIncome {
      width: 90%;
      margin: 26px auto;
    }
    .selectIncome .bootstrap-select .dropdown-menu.open {
      width: 100%;
    }
    .selectIncome .bootstrap-select {
      width: 100% !important;
    }
    .selectIncome > .bootstrap-select > .dropdown-toggle {
      width: 100%;
    }

    .selectTripDays {
      display: block;
      width: 100%;
      margin: 12px auto;
    }
    .selectTripDays .bootstrap-select .dropdown-menu.open {
      width: 100%;
      min-height: auto !important;
      padding: 0;
    }

    .multiTripOption {
      display: none;
    }
  }
`;

export const Rows = styled(Row)`
  padding: 0;
  margin: ${({ margin }) => margin || "0"};
`;

export const ErrorDiv = styled.div`
  position: absolute;
  bottom: -20px;
  width: 480px;
  @media only screen and (max-width: 767px) {
    bottom: -32px;
    left: 15px;
    width: auto;
  }
`;
