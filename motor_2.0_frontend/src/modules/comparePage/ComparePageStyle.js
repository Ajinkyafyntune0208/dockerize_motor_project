import styled from "styled-components";

// userd in comparePage
export const TopDiv = styled.div`
  .compareConatiner {
    width: 100%;
  }
  .compare-header {
    display: flex;
    justify-content: center;
    position: inherit;
    align-items: center;
    font-size: 28px;
    margin-top: 20px;
    margin-bottom: 30px;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : ` Merriweather, Georgia, serif`};
    @media screen and (max-width: 767px) {
      font-size: 13px;
    }
  }
  .compare-page {
    z-index: 1;
    margin: auto;
    position: relative;
    &-container {
      max-width: 1110px;
      padding: 0px 0px 80px 0px;
      @media only screen and (max-width: 1177px) and (min-width: 768px) {
        & {
          max-width: 710px;
        }
      }
      @media screen and (max-width: 767px) {
        padding: 0 7px;
        width: 100%;
      }
    }
    &-features .cd-features-list li,
    .compare-products-wrap &-features .top-info {
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      font-size: 14px;
      line-height: 19px;
      color: #333;
      line-height: 14px;
      padding: 0;
      text-align: left;
    }
    & .laxmigreeting__wrapper {
      margin-top: -33px;
    }
    &__top {
      margin-top: 60px;
      display: flex;
      background-color: #ffffff;
      z-index: 100;
    }
    &__bottom {
      display: flex;
      background-color: #ffffff;
      margin-bottom: 30px;
    }
    &__laxmi-text {
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      font-size: 24px;
      line-height: 24px;
      color: #333;
      text-align: center;
      margin-top: -19px;
    }
    &__email-quotes-btn {
      display: none;
      position: absolute;
      top: 0;
      right: 0;
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      font-size: 14px;
      line-height: 14px;
      color: #333 !important;
      text-decoration: underline;
      margin-top: 18px;
      border: none;
      background: none;
      z-index: 99;
      &:hover {
        cursor: pointer;
        text-decoration: none;
      }
    }
    &__back-btn {
      text-transform: uppercase;
      border: none;
      background: none;
      z-index: 99;
      width: max-content;
      display: flex;
      align-items: center;
      color: #808080;
      font-size: 14px;
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      position: absolute;
      margin-top: 18px;
      left: 46px;
      cursor: pointer;
      & svg {
        width: 52px;
        height: 20px;
      }
      & .compare-page__back-text {
        margin-left: 1px;
        margin-bottom: 2px;
      }
    }
    .compare-products-wrap {
      position: relative;
      & .top-info {
        position: relative;
        height: 220px;
        text-align: center;
        padding: 0;
        -webkit-transition: height 0.3s;
        -moz-transition: height 0.3s;
        transition: height 0.3s;
        background-color: #ffffff;
        z-index: 1500;
      }
      & .planOptionHead {
        font-family: ${({ theme }) =>
          theme.mediumFont?.fontFamily || "Inter-SemiBold"};
        font-size: 16px;
        line-height: 19px;
        // color: #333;
        border-bottom: 1px solid #e3e4e8;
        margin-right: 10px;
      }
      & .planOptionNameSub {
        font-size: 12px;
        line-height: 16px;
        color: #808080;
        overflow: revert !important;
        margin-top: -16px;
        margin-bottom: 0px !important;
        height: 52px !important;
      }
      & .productDividerPadding {
        margin-bottom: 16px !important;
      }
      & .planOptionNameSub {
        font-size: 12px;
        line-height: 16px !important;
        color: #808080;
        overflow: revert !important;
        margin-top: -16px;
        margin-bottom: 0px !important;
        height: 52px !important;
      }
      & .planStickyHeader {
        position: fixed !important;
        top: 0;
        border-top-width: 0;
      }
      & .compare-page-features .planStickyHeader {
        position: fixed !important;
        top: 0;
        width: 275px;
        border: none;
      }
      & .planStickyHeader ~ .cd-features-list {
        margin-top: 320px;
      }
      @media screen and (min-width: 768px) and (max-width: 1177px) {
        & .productDividerPadding {
          margin-bottom: 0 !important;
        }
      }
      @media screen and (max-width: 767px) {
        margin-top: 10px;
        & .top-info {
          height: 150px !important;
        }
        & .compare-page-features .cd-features-list li,
        .compare-products-wrap .compare-page-features .top-info {
          font-size: 10px;
          line-height: 14px !important;
          color: #7a7d80;
          border: none;
        }
        & .planStickyHeader {
          position: relative !important;
          top: auto;
          border: none !important;
          border-left-width: 0;
          border-top-width: 0;
        }
        & .compare-page-features .planStickyHeader {
          position: relative !important;
          top: auto;
          border: none !important;
          width: auto;
          border-left-width: 0;
          border-top-width: 0;
        }
        & .planStickyHeader ~ .cd-features-list {
          margin-top: 0;
        }
          & .planStickyHeader .div {
          display:flex;
        }
        & .planOptionHead {
          font-size: 12px;
          width: 100%;
          margin-top: -1px;
        }
        & .planOptionNameSub {
          display: block;
          height: 0 !important;
          overflow: hidden !important;
          margin-bottom: 53px !important;
        }
        & .productDividerPadding {
          margin-bottom: 7px !important;
        }
      }
    }
    @media screen and (min-width: 768px) and (max-width: 1177px) {
      &__back-btn {
        left: 32px;
      }
      &__laxmi-text {
        font-size: 20px;
      }
      & .laxmigreeting__wrapper {
        margin-top: -19px !important;
        margin-bottom: 2px !important;
        & .laxmiGreeting__img {
          height: 80px;
          width: 80px;
        }
      }
      &__email-quotes-btn {
        display: inline-block;
      }
    }
    @media screen and (max-width: 767px) {
      &__back-btn {
        font-size: 13px;
        margin-top: 7px;
        left: 18px;
        text-transform: capitalize;
        & .backSvg {
          margin-bottom: 2px !important;
          margin-right: -3px !important;
        }
        & .compare-page__back-text {
          font-size: 13px;
        }
      }
      &__laxmi-text {
        font-size: 13px;
      }
      & .laxmigreeting__wrapper {
        margin-top: -18px !important;
        margin-bottom: 3px !important;
        & .laxmiGreeting__img {
          height: 80px;
          width: 80px;
        }
      }
      &__email-quotes-btn {
        display: inline-block;
        font-size: 13px;
        line-height: 14px;
        color: #333;
        text-decoration: none;
        right: 15px;
        margin-top: 11px;
      }
      &-features .cd-features-list li,
      .compare-products-wrap &-features .top-info {
        font-size: 10px;
        line-height: 14px;
        color: #7a7d80;
        border: none;
      }
      &-features .top-info .planOptionHead {
      }
    }
  }
  .two-line-height {
    height: 40px;
  }
  .styledDiv {
    display: flex;
    justify-content: space-between;
    width: 90px;
  }
  @media (max-width: 767px) {
    .styledDiv {
      display: flex;
      justify-content: space-between;
      width: 70px;
    }
  }
  .compare-page {
  }
  .compare-header {
    font-size: 2rem;
    /* margin-left: 8.5em; */
  }
  @media (max-width: 768px) {
    .compare-page {
    }
    .compare-header {
      font-size: 0.95rem;
      text-align: center;
      margin-top: 60px;
    }
    .backBtn button {
      top: 56px !important;
      left: 0px !important;
    }
  }
`;

// used in compare page
export const PdfDiv = styled.div`
  border: ${({ theme }) =>
    theme.floatButton?.floatBorder || "1px solid #bdd400"};

  .pdf_icon {
    color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
    
    
  }
`;

// userd in content and contentModal page
export const CompareButton = styled.button`
  background: ${({ theme }) => theme.comparePage?.color || "#bdd400"};
`;

// userd in content and contentModal page
export const NoPlansDiv = styled.div`
  background: ${({ theme }) => theme.NoPlanCard?.background || "#f7f7fa"};
  // border: ${({ theme }) => theme.NoPlanCard?.border || "2px dotted green"};
`;

// userd in content and contentModal page
export const CardDiv = styled.div`
  background: ${({ theme }) =>
    theme.CardPop?.background || "rgb(18 211 77 / 6%)"};
  border: ${({ theme }) => theme.CardPop?.border || "1px solid green"};
`;
// used in contentModal page
export const TopPop2 = styled.div`
  max-width: 100%;
  overflow-x: hidden;
  position: relative;
  height: ${({ innerHeight }) => (innerHeight ? innerHeight + "px" : "100vh")};
  .add_plans {
    font-family: ${({ theme }) =>
      theme.mediumFont?.fontFamily || "Inter-SemiBold"};
  }
  .productCheck {
    background: ${({ theme }) => theme.comparePage?.color || "#bdd400"};
  }
  h4 {
    color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  }
`;
// used in contentModal page
export const StyledDiv2 = styled.div`
  position: absolute;
  right: 10px;
  top: -12px;
  z-index: 101;
  .round-label::after {
    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid #62636a"};
  }

  .group-check input[type="checkbox"]:checked + label::before {
    transform: scale(1);
    background-color: ${({ theme }) => theme?.QuoteCard?.color || "#bdd400"};

    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid #bdd400"};
  }
`;
// used in content page
export const TopPop = styled.div`
  .add_plans {
    font-family: ${({ theme }) =>
      theme.mediumFont?.fontFamily || "Inter-SemiBold"};
  }
  h4 {
    color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  }
`;
// used in content page
export const StyledDiv1 = styled.div`
  position: absolute;
  right: 47px;
  top: 1px;
  z-index: 101;
  .round-label::after {
    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid  #62636a"};
  }

  .group-check input[type="checkbox"]:checked + label::before {
    transform: scale(1);
    background-color: ${({ theme }) => theme?.QuoteCard?.color || "#bdd400"};

    border: ${({ theme }) =>
      theme?.QuoteCard?.borderCheckBox || "1px solid #bdd400"};
  }
`;
