import styled from "styled-components";

export const TopDiv = styled.div`
  .compare-products-list {
    & .top-info {
      font-family: ${({ theme }) =>
        theme.regularFont?.fontFamily || "Inter-Regular"};
      font-size: 13px;
      // line-height: 19px;
      color: #333;
      line-height: 14px;
      padding: 0;
      text-align: left;
      position: relative;
      height: 250px;
      text-align: center;
      padding: 0;
      -webkit-transition: height 0.3s;
      -moz-transition: height 0.3s;
      transition: height 0.3s;
    }
    & .cd-products-columns {
      /* width: 1200px; */
      margin-left: 260px;
      list-style: none;
      padding: 0;
      &::after {
        clear: both;
        content: "";
        display: table;
      }
    }
    @media screen and (min-width: 768px) and (max-width: 1177px) {
      & .cd-products-columns {
        width: 554px;
        margin-left: 156px;
      }
    }
    @media screen and (max-width: 767px) {
      & .cd-products-columns {
        margin-left: 0px !important;
      }
    }
  }
`;
