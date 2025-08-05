import styled from "styled-components";

export const TableWrapper = styled.div`
  font-size: 12px;
  padding-top: 84px;
  .table td,
  .table th {
    vertical-align: inherit !important;
  }
  .tableOne {
    margin-bottom: 0px;
  }
  .firstTableValue {
    padding: 0px;
    height: 39px !important;
    border-width: 0px;
  }
  .EmptyHide {
    visibility: hidden;
  }
  .premiumValues {
    padding: 0px;
    height: 39px !important;
    border-width: 0px;
  }
  .premiumTable {
    margin-top: 65px;
    margin-bottom: 0px;
  }
  .addonValues {
    padding: 0px;
    height: 39px !important;
    border-width: 0px;
  }
  .addonTable {
    margin-top: 66px;
    margin-bottom: 0px;
  }
  .accessoriesTable {
    margin-top: 66px;
  }
  .accessoriesValues {
    padding: 0px;
    height: 39px;
    border: 0px solid transparent;
  }
  .additionalCoverTable {
    margin-top: 66px;
  }
  .additionalCoverValues {
    padding: 0px;
    height: 39px;
    border: 0px solid transparent;
  }
  .discountTable {
    margin-top: 66px;
  }
  .discountValues {
    padding: 0px;
    height: 39px;
    border: 0px solid transparent;
  }
  @media screen and (max-width: 1178px) {
    font-size: 10px;
  }
  @media screen and (max-width: 768px) {
    padding-top: 45px;
    .table td,
    .table th {
      vertical-align: bottom !important;
    }
    .tableOne {
      margin-bottom: 0px;
    }
    .firstTableValue {
      height: 48px !important;
    }
    .premiumTable {
      margin-top: 70px;
    }
    .premiumValues {
      /* padding-top: 17px; */
      height: 48px !important;
    }

    .addonValues {
      height: 74px !important;
      /* padding-top: 10px; */
    }
    .addonTable {
      margin-top: 60px !important;
    }
    .accessoriesTable {
      margin-top: 62px;
    }
    .accessoriesValues {
      height: 48px;
      vertical-align: bottom !important;
    }
    .additionalCoverTable {
      margin-top: 56px;
    }
    .additionalCoverValues {
      height: 48px;
    }
    .discountTable {
      margin-top: 55px;
    }
    .discountValues {
      height: 48px;
    }
  }
`;

export const RecPlanBuyBtn = styled.button`
  position: relative;
  background-color: ${({ theme, themeDisable }) =>
    themeDisable ? "#787878" : theme.comparePage?.color || "#d7df23"};
  border: ${({ theme, themeDisable }) =>
    themeDisable
      ? "1px solid #787878"
      : theme.comparePage?.border || "solid 1px #d7df23"};
  color: #fff;
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "30px"} !important;
  &:hover {
    background: ${({ theme, themeDisable }) =>
      themeDisable ? "#787878" : theme.comparePage?.color || "#fff"};
    color: ${({ theme }) => theme.comparePage?.textColor || "#d7df23"};
  }
  .withGstText {
    position: absolute;
    color: black;
    bottom: -21px;
    left: 0;
    right: 0;
    font-size: 11px;
    @media (max-width: 767px) {
      bottom: unset;
      top: -19px;
      font-size: 9px;
    }
  }
`;
export const TopLi = styled.li``;
export const TopInfo = styled.div`
  border: ${({ isRenewal, theme }) =>
    isRenewal
      ? theme?.Registration?.proceedBtn?.background
        ? `1px solid ${theme?.Registration?.proceedBtn?.background}`
        : `1px solid #4ca729`
      : `1px solid #d0d0d0d0`};
  position: ${(props) => (props.fixed ? "fixed !important" : "relative")};
  top: ${(props) => (props.fixed ? "15px" : "unset")};
  box-shadow: ${(props) =>
    props.fixed
      ? "rgb(0 0 0 / 10%) 0px 8px 25px -5px, rgb(0 0 0 / 4%) 0px 10px 10px -5px"
      : "rgb(0 0 0 / 10%) 0px 8px 25px -5px,rgb(0 0 0 / 4%) 0px 10px 10px -5px;"};
`;
export const NoAddonCotainer = styled.div`
  position: relative;
  min-height: 39px;
  //	bottom: 5px;
  position: relative;
  top: ${(props) => (props.amount ? "5px" : "")};
`;

export const StyledDiv1 = styled.div`
  position: absolute;
  right: 47px;
  top: 1px;
  z-index: 101;
  .round-label::after {
    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid  #d4d5d9"};
  }

  .group-check input[type="checkbox"]:checked + label::before {
    transform: scale(1);
    background-color: ${({ theme }) => theme?.QuoteCard?.color || "#bdd400"};

    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid #bdd400"};
  }
`;

export const CardDiv = styled.div`
  background: ${({ theme }) =>
    theme.CardPop?.background || "rgb(18 211 77 / 6%)"};
  border: ${({ theme }) => theme.CardPop?.border || "1px solid green"};
`;

export const CompareButton = styled.button`
  background: ${({ theme }) => theme.comparePage2?.background || "green"};
`;

export const NoPlansDiv = styled.div`
  background: ${({ theme }) => theme.NoPlanCard?.background || "#f7f7fa"};
  // border: ${({ theme }) => theme.NoPlanCard?.border || "2px dotted green"};
`;

export const AddPlanIcon = styled.i`
  color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
`;

export const CloseContainer = styled.i`
  box-shadow: rgb(0 0 0 / 10%) 0px 8px 25px -5px,
    rgb(0 0 0 / 4%) 0px 10px 10px -5px;
`;

export const DataCard = styled.div`
  position: relative;
  top: ${(props) => (props.fixed ? "220px !important" : "")};

  .badge {
    font-size: 100%;
    font-weight: 500;
  }
`;

export const TopDiv = styled.div`
  .compare-page-product {
    width: 276px;
    position: relative;
    float: left;
    text-align: center;
    margin-right: 11px;
    font-family: ${({ theme }) =>
      theme.regularFont?.fontFamily || "Inter-Regular"};
    font-size: 14px;
    line-height: 18px;
    color: #333333;
    /* background-color: #f7fdff; */
    -webkit-transition: opacity 0.3s, visibility 0.3s, -webkit-transform 0.3s;
    -moz-transition: opacity 0.3s, visibility 0.3s, -moz-transform 0.3s;
    transition: opacity 0.3s, visibility 0.3s, transform 0.3s;
    .planSubOptionVal {
      margin-bottom: 18px;
    }
    &:last-child {
      margin-right: 0;
    }
    &__logo-wrap {
      display: inline-flex;
      height: 60px;
      //width: 60px;
      margin: 24px auto;
    }
    & .productRecLabel {
      width: 275px;
      position: absolute;
      top: -40px;
      left: -1px;
      background: #107591;
      font-family: ${({ theme }) =>
        theme.mediumFont?.fontFamily || "Inter-SemiBold"};
      font-size: 12px;
      line-height: 24px;
      color: #fff;
      text-align: center;
      width: 275px;
      padding: 8px 0;
      border-radius: 2px 20px 0 0;
    }
    & .top-info {
      width: 250px;
      & img {
        object-fit: contain;
        height: auto;
        width: 160px;
        position: relative;
        //	transform: translateX(-20%);
        // margin: 24px 0;
      }
      & .planName {
        font-family: ${({ theme }) =>
          theme.mediumFont?.fontFamily || "Inter-SemiBold"};
        font-size: 14px;
        line-height: 20px;
        color: #333;
        margin-bottom: 22px;
      }
      & .planAmt {
        font-family: ${({ theme }) =>
          theme.regularFont?.fontFamily || "Inter-Regular"};
        font-size: 20px;
        line-height: 25px;
        color: #333333;
        margin-bottom: 8px;
      }
      & .recPlanBuyBtn {
        height: 40px;
        width: 148px;
        border-radius: 4px;
        outline: none;
        font-family: ${({ theme }) =>
          theme.mediumFont?.fontFamily || "Inter-SemiBold"};
        font-size: 15px;
        line-height: 24px;
        border-radius: 30px;
      }
    }
    &.productRec .recPlanBuyBtn {
      width: 156px;
    }
    &--1 {
      width: 850px;
      & .productRecLabel,
      & .top-info {
        width: 849px;
      }
    }
    &--2 {
      width: 419.5px;
      & .productRecLabel,
      & .top-info {
        width: 415px;
      }
    }
    & .cd-features-list {
      padding: 0;
      list-style: none;
      & li {
        padding-top: 95px;
        font-size: 12px;
        color: ${({ theme }) => theme.regularFont?.textColor || "#333"};

        & .planSubOptionValue {
          padding-bottom: 0;
          min-height: 39px;
          overflow: hidden;
          &:last-child {
            padding-bottom: 0;
          }
        }
      }
    }
    @media screen and (min-width: 768px) and (max-width: 1177px) {
      width: 177.333px !important;
      .addonValueText {
        font-size: 10px !important;
      }
      & .productRecLabel,
      & .top-info {
        // width: 176.333px !important;
        width: 173.5px;
        & img {
          height: 60px;
        }
      }
      &--1 {
        width: 554px !important;
        & .productRecLabel,
        & .top-info {
          width: 553px !important;
        }
      }
      &--2 {
        width: 271.5px !important;
        & .productRecLabel,
        & .top-info {
          width: 267.5px !important;
        }
      }
    }
    @media screen and (max-width: 767px) {
      width: 33% !important;
      margin-right: 0;
      border: none;
      background: none;
      .addonValueText {
        font-size: 9px !important;
      }
      & .productRecLabel {
        display: none;
      }
      &__logo-wrap {
        width: 34px;
        height: 34px;
        margin: 8px auto;
      }
      & .top-info {
        width: 99.672%;
        height: 220px !important;
        & img {
          width: 250%;
          height: 40px;
          transform: translateX(-30%);
        }
        & .planName {
          font-size: 11px;
          line-height: 16px;
          /* border-top: 1px solid #e3e4e8; */
          margin-top: 1px;
          padding-top: 10px;
          height: 44px;
          max-height: 44px;
          overflow: hidden;
          margin-bottom: 0;
        }
        & .planAmt {
          font-size: 14px;
          line-height: 18px;
          margin-bottom: 4px;
        }
      }
      &.productRec .recPlanBuyBtn,
      & .top-info .recPlanBuyBtn {
        width: 80%;
      }
      & .cd-features-list {
        border-top: none;
        & li {
          font-size: 11px;
          line-height: 15px;
          color: #333;
          font-family: ${({ theme }) =>
            theme.mediumFont?.fontFamily || "Inter-SemiBold"};
          padding-top: 72px;
          padding-bottom: 31px;
          border-bottom: none;
          & .planSubOptionValue {
            height: 28px;
            margin-bottom: 8px !important;
          }
          &.features-list-Discount {
            // padding-top: 60px !important;
          }
          &.features-list-top-li {
            padding-top: 60px !important;
          }
        }
      }
    }
  }
  .compare-page-product {
    /* background: #d0e438; */
  }
  .compare-products-list .top-info {
    /* background: #fff; */
  }
  .compare-page-product .cd-features-list li .planSubOptionValue {
    font-size: 14px;
    @media screen and (min-width: 768px) and (max-width: 1177px) {
      font-size: 10px;
    }
  }
  .compare-page-product .top-info .planName {
    /* color: #fff; */
    margin-top: 10px;
  }
  .check {
    color: green;
    font-size: 1.5rem;
  }
  .cross {
    color: red;
    font-size: 1.5rem;
  }
  @media (max-width: 768px) {
    .longNameText {
      min-height: 60px !important;
      font-size: 9px !important;
      padding: 0px 10px 0px 0px !important;
    }
    .compare-page-product {
      /* box-shadow: none;
        border-radius: 0px;
        background: #fff; */
    }
    .compare-products-list .top-info {
      /* border-bottom: none;
        border-radius: 0px;
        background: #fff!important; */
    }
    .compare-page-product .top-info .planName {
      color: black;
      margin-top: 4px;
    }
    .compare-page-product .top-info .recPlanBuyBtn {
      font-size: 8px;
      width: 70px;
      height: 26px;
      margin-top: 13px;
    }
    .compare-page-product .cd-features-list li .planSubOptionValue {
      font-size: 9px;
    }
    .check {
      font-size: 1rem;
    }
    .cross {
      font-size: 1rem;
    }
  }
  .newProductList {
    padding: "10px 32px";
    max-height: "400px";
    overflow: "scroll";
    overflow-x: "hidden !important";
  }
  @media (max-width: 766px) {
    .premiumBreakupLi {
      margin-top: -6px !important;
      padding-top: 60px !important;
    }
    .addonDetailsLi {
      margin-top: -10px !important;
      padding-top: 76px !important;
    }
    .accessoriesBenifits {
      margin-top: 0px !important;
    }
    .accessoriesBenifits1 {
      margin-top: -33px !important;
    }
    .icUsp {
      padding-top: 80px !important;
    }
    .keyAddon {
      padding-top: 12px !important;
    }
    .engineDiv {
      padding-top: 19px !important;
    }
    .ncbDiv {
      padding-top: 25px !important;
    }
    .consumableDiv {
      padding-top: 31px !important;
    }
    .tyreSecureDiv {
      padding-top: 37px !important;
    }
    .returnVoiceDiv {
      padding-top: 25px !important;
    }
    .roadSideDiv {
      padding-top: 6px !important;
    }
    .lossDiv {
      padding-top: 43px !important ;
    }
    .additionalCoverDiv {
      margin-top: -45px !important;
    }
    .totalPremiumDiv {
      padding-top: 8px !important;
    }
    .eMedicalExpenses {
      padding-top: 43px !important;
    }
  }
  .buyNowBtn {
    padding: 10px 0px;
    font-weight: bold;
    font-size: 0.65rem;
    color: ${({ theme }) => theme.floatButton?.whiteColor || ""};
    background: ${({ theme }) => theme.comparePage?.color || "#d7df23"};
    border: ${({ theme }) => theme.comparePage?.border || "none"};
    border-radius: ${({ theme }) =>
      theme.QuoteBorderAndFont?.borderRadius || "30px"} !important;
  }

  @media (max-width: 766px) {
    .mobile-top-info {
      position: fixed !important;
      width: 30% !important;
      top: 2%;

      @media (max-width: 600px) {
        width: 30% !important;
      }
      @media (max-width: 450px) {
        width: 30% !important;
      }
    }
    .mobile-data-card {
      margin-top: 160px;
    }
  }

  @media (max-width: 767px) {
    .buy_now_div {
      width: 85%;
      margin: auto;
    }
    .recPlanBuyBtn {
      width: 90% !important;
      font-size: 15px !important;
    }
  }
  @media (max-width: 400px) {
    // .buy_now_div {
    //   width: 95%;
    //   margin: auto;
    // }
    .recPlanBuyBtn {
      // width: 100% !important;
      font-size: 14px !important;
    }
  }
  @media (max-width: 350px) {
    // .buy_now_div {
    //   width: 100%;
    //   margin: auto;
    // }
    .recPlanBuyBtn {
      // width: 100% !important;
      font-size: 12px !important;
    }
  }
`;

export const TopPop = styled.div`
  .add_plans {
    font-family: ${({ theme }) =>
      theme.mediumFont?.fontFamily || "Inter-SemiBold"};
  }
`;

export const FoldedRibbon = styled.div`
  width: 104px;
  position: absolute;
  background: ${({ theme }) => theme.Tab?.color || "#4ca729"};
  color: #fff;
  font-weight: bold;
  display: flex;
  -webkit-align-items: center;
  -webkit-box-align: center;
  -ms-flex-align: center;
  align-items: center;
  -webkit-box-pack: center;
  -webkit-justify-content: center;
  -ms-flex-pack: center;
  justify-content: center;
  -webkit-transform: rotate(-45deg);
  -ms-transform: rotate(-45deg);
  transform: rotate(0deg);
  box-shadow: 0 2px 3px rgba(0, 0, 0, 0.3);
  z-index: 9;
  clip-path: polygon(100% 0, 95% 50%, 100% 100%, 0 100%, 0 0);
  font-size: ${({ handleSize }) => (handleSize ? "9.5px" : "11.5px")};
`;
