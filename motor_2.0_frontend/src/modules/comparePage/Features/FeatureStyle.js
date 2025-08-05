import styled from "styled-components";

export const PlanOptionHead = styled.div`
  color: ${({ theme }) => theme.comparePage?.color || "#55d400 !important"};
  border-bottom: none !important;
  .planOptionText {
    background: ${({ theme }) =>
      theme.comparePage2?.lg ||
      "-webkit-linear-gradient(-134deg, #ffffff, #d7df23) "};
    padding: 5px;
    color: white;
    font-size: 14px;
    border-radius: 0px 00px 0px 0px;
    width: 475%;
    border-left: ${({ theme }) =>
      theme.comparePage2?.borderHeader || " 5px solid #a6ac1a "};
    height: 40px;
    display: flex;
    align-items: center;
    padding: 0px 0px 0px 15px;
    margin-top: 10px;
    @media (max-width: 768px) {
      font-size: 10px;
      border-radius: 0px;
      width: 100%;
    }
  }
`;

export const VehicleDetails = styled.div`
  // box-shadow: rgb(0 0 0 / 10%) 0px 8px 25px -5px,
  // 	rgb(0 0 0 / 4%) 0px 10px 10px -5px;
  background: ${(props) => (props.fixed ? "#f7f7fa" : "#f7f7fa")};
  height: ${(props) => (props.fixed ? "235px" : "220px")};
  position: ${(props) => (props.fixed ? "fixed !important" : "relative")};
  bottom: ${(props) => (props.fixed ? "unset" : "17px")};
  top: ${(props) => (props.fixed ? "0px" : "unset")};
  min-width: ${(props) => (props.fixed ? "250px" : "unset")};
  width: ${(props) => (props.fixed ? "1107px" : "250px")};
  padding: 25px;
  .vehicleName {
    font-weight: 600;
    font-size: 14px;
    line-height: 24px;
    padding: 10px 0px;
    border-bottom: 1px solid #6b6e71;
    text-align: left;
    max-width: 200px;
  }
  .policyType {
    font-family: ${({ theme }) =>
      theme.regularFont?.fontFamily || "Inter-Regular"};
    font-size: 12px;
    padding: 10px 0px;
    color: #6b6e71;
    text-align: left;
  }
  .dates {
    font-family: ${({ theme }) =>
      theme.regularFont?.fontFamily || "Inter-Regular"};
    font-size: 12px;
    padding: 5px 0px;
    text-align: left;
  }

  @media screen and (min-width: 768px) and (max-width: 1177px) {
    min-width: ${(props) => (props.fixed ? "220px" : "unset")};
    width: ${(props) => (props.fixed ? "710px" : "220px")};
    .vehicleName {
      font-size: 12px;
      line-height: 18px;
      padding: 5px 0px;
      max-width: 140px;
    }
    .policyType {
      font-size: 10px;
      padding: 5px 0px;
    }
    .dates {
      font-size: 10px;
      padding: 5px 0px;
    }
  }

  @media screen and (max-width: 767px) {
    display: none;
    .vehicleName {
      font-size: 12px;
      padding: 5px 0px;
      line-height: 18px;
      max-width: 140px;
    }
    .policyType {
      font-size: 10px;
      padding: 5px 0px;
    }
    .dates {
      font-size: 10px;
      padding: 5px 0px;
    }
  }
`;

export const FilterMenuBoxCheckConatiner = styled.div`
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
  }
  .filterMenuBoxCheck label {
    font-family: ${({ theme }) =>
      theme.regularFont?.fontFamily || "Inter-Regular"};
    font-size: 14px;
    color: #333;
    @media screen and (max-width: 767px) {
      font-size: 11px !important;
    }
  }
  .filterMenuBoxCheck label:before {
    border: 1px solid #000000;
    @media screen and (max-width: 767px) {
      padding-left: 30px;
      height: 14px;
      padding: 1px 0 0;
      vertical-align: top;
      width: 14px;
    }
  }

  @media screen and (min-width: 768px) and (max-width: 1177px) {
    .filterMenuBoxCheck label {
      font-size: 10px !important;
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      font-size: 14px;
      color: #333;
    }
  }
`;

export const TopDiv = styled.div`
  .compare-page {
    &-features {
      position: absolute;
      // top: 124px;
      top: 18px;
      left: 0;
      width: 292px;
      padding-right: 48px;
      &::after {
        content: "";
        position: absolute;
        top: 0;
        left: 100%;
        width: 4px;
        height: 100%;
        background-color: transparent;
        background-image: -webkit-linear-gradient(
          left,
          rgba(0, 0, 0, 0.06),
          transparent
        );
        background-image: linear-gradient(
          to right,
          rgba(0, 0, 0, 0.06),
          transparent
        );
        opacity: 0;
      }
      & .top-info {
        font-family: ${({ theme }) =>
          theme.regularFont?.fontFamily || "Inter-Regular"};
        line-height: 19px;
        color: #333;
        line-height: 14px;
        padding: 0;
        text-align: left;
        & .planOptionHead {
          border-bottom: none !important;
          margin-right: 60px;
          padding-bottom: 6px;
          text-align: justify;
        }
        & .planOptionFName {
          margin-top: 24px;
          margin-bottom: 26px;
          text-align: justify;
        }
      }
      & .cd-features-list {
        padding: 0;
        list-style: none;
        & li {
          font-family: ${({ theme }) =>
            theme.regularFont?.fontFamily || "Inter-Regular"};
          font-size: 14px;
          line-height: 19px;
          // color: ${({ theme }) => theme.regularFont?.textColor || "#333"};
          & .planOptionName {
            margin-bottom: 0;
            height: 39px;
            overflow: hidden;
            line-height: 19px;
            border-bottom: 1px solid #d0d0d0;
            width: 453%;
            padding: 10px 0px 0px 20px;
            font-weight: 300 !important;
            &:last-child {
            }
            @media (max-width: 768px) {
              border-top: 1px solid;
            }
          }
        }
      }
      @media screen and (min-width: 768px) and (max-width: 1177px) {
        width: 150px;
        padding-right: 4px;
        & .cd-features-list {
          & li {
            font-size: 10px !important;
          }
          & .planOptionHead {
            font-size: 12px;
            margin-right: 0;
          }
          & .planOptionName {
            font-size: 10px !important;
            font-weight: 300 !important;
          }
        }
      }
      @media screen and (max-width: 767px) {
        width: 100%;
        padding-right: 0;
        top: 10px;
        & .top-info .planOptionFName {
          margin-top: 11px;
        }
        & .cd-features-list {
          border: none;
        }
        & .cd-features-list li .planOptionName {
          margin-bottom: 14px;
          height: 34px;
          line-height: 14px;
          border-bottom: none;
          width: 100% !important;
          padding: 2px 8px 0px 0px;
          &:last-child {
          }
        }
        .longNameText {
          min-height: 60px;
        }
      }
    }
  }
  .compare-page .compare-products-wrap .planOptionHead {
    font-size: 19px !important;
  }
  .compare-page .compare-products-wrap .planOptionFName {
    font-size: 16px;
    font-weight: bold;
    margin-top: 35px !important;
  }
  .compare-page-features .cd-features-list li .planOptionName {
    font-size: 14px;
    font-weight: bold;
  }
  @media (max-width: 768px) {
    .compare-page .compare-products-wrap .planOptionHead {
      font-size: 11px !important;
    }
    .compare-page .compare-products-wrap .planOptionFName {
      font-size: 11px;
      font-weight: bold;
    }
    .compare-page-features .cd-features-list li .planOptionName {
      font-size: 11px;
      font-weight: bold;
    }
  }
  @media (max-width: 768px) {
    .addOnDetails {
      margin-top: 30px;
    }
    .icContent {
      border-top: none !important;
      border-bottom: 1px solid !important;
    }
  }
  .cd-features-list li {
    color: ${({ theme }) => theme.regularFont?.textColor + "!important" || ""};
  }
`;
export const PDFButton = styled.div`
  display: ${import.meta.env.VITE_BROKER === "ABIBL"
    ? "flex"
    : "none"} !important;
  justify-content: center;
  align-items: center;
  width: 100%;
  margin-top: 20px;
`;
