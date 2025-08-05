import { Row } from "react-bootstrap";
import styled from "styled-components";

export const DivContent = styled.div`
  margin-top: -55px;
  margin-left: 5px;
  margin-right: 5px;
  margin-bottom: 10px;
`;
export const ProposalRibbon = styled.p`
  color: white;
  font-size: 10px;
  margin-left: 25px;
  margin-top: 25px;
  height: px;
  position: absolute;
  opacity: 0.8;
  background: ${({ theme }) => theme.Tab?.color || "#4ca729"};
  display: inline-block;
  padding: 0px 5px 0px 10px;
  left: -5px;
  top: 15px;
  z-index: 1;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `Arial`};
  &::before {
    content: "";
    position: absolute;
    left: 0px;
    top: -5px;
    border-top: 2.5px solid transparent;
    border-right: 2.5px solid ${({ theme }) => theme.Tab?.color || "#4ca729"};
    border-bottom: 2.5px solid ${({ theme }) => theme.Tab?.color || "#4ca729"};
    border-left: 2.5px solid transparent;
  }
  &::after {
    content: "";
    position: absolute;
    top: 0px;
    right: -14px;
    border-top: 8px solid ${({ theme }) => theme.Tab?.color || "#4ca729"};
    border-right: 7px solid transparent;
    border-bottom: 7px solid ${({ theme }) => theme.Tab?.color || "#4ca729"};
    border-left: 7px solid ${({ theme }) => theme.Tab?.color || "#4ca729"};
  }
  @media (max-width: 767px) {
    top: -5px;
  }
`;

export const TagLineDiv = styled.div`
  width: 100%;
`;

export const HeaderTagLine = styled.h6`
  font-size: 14px;
  font-weight: bold;
`;

export const PTagLine = styled.p`
  font-size: 80%;
  margin-top: -5px;
  font-weight: 400;
`;

export const DivSumIns = styled.div`
  margin-bottom: 15px;
`;

export const HeaderSumIns = styled.h6`
  font-size: 11px;
  color: gray;
  font-weight: 1000;
`;

export const PSumIns = styled.p`
  font-size: 14.5px;
  font-weight: 700;
  margin-top: -7px;
`;

export const DivPremium = styled.div`
  margin-bottom: 15px;
  display: flex;
  margin-top: -10px;
`;

export const HeaderPremium = styled.h6`
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 5px;
`;

export const RowTagPlan = styled(Row)`
  margin-bottom: 10px;
`;

export const UlTag = styled.ul`
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  margin-top: -10px;
  list-style: none;
  line-height: 18px;
  padding: 8px 0;
`;

export const LiTag = styled.li`
  font-size: 11px;
  line-height: 18px;
  font-weight: 400;
  margin-bottom: 5px;
`;

export const SpanTagRight = styled.span`
  float: right;
  text-align: right;
  ${({ length }) =>
    length &&
    `
      width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      `}
`;

export const DivTotal = styled.div`
  border: ${({ theme }) =>
    theme?.ReviewCard?.borderDashed || "1px dashed #f8cf39"};
  margin-top: -10px;
  padding: 5px 10px;
`;

export const RowTag = styled(Row)`
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  margin-top: 15px;
`;

export const DivTag = styled.div`
  width: 100%;
  padding: 5px 6px;
`;

export const PTag = styled.p`
  padding: 5px 6px;
  font-weight: 600;
  margin-bottom: 15px;
  font-size: 14px;
`;

export const StrongTag = styled.strong`
  font-size: 25px;
  @media (max-width: 1000px) {
    font-size: 20px;
  }
`;

export const DivBenefits = styled.div`
  margin-top: ${({ margin }) => (margin ? "2px" : "10px")};
  width: 100%;
`;

export const PBenefits = styled.p`
  font-size: 13px;
  font-weight: 400;
  .text-danger {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB" &&
      theme.primary &&
      theme.primary}!important;
  }
  .badge-danger {
    background: ${({ theme }) =>
      theme?.sideCardProposal?.iconColor &&
      theme?.sideCardProposal?.iconColor}!important;
  }
  .fa-star:before {
    color: ${({ theme }) =>
      theme?.sideCardProposal?.iconColor &&
      theme?.sideCardProposal?.iconColor}!important;
  }
`;

export const DivDownload = styled.div`
  margin-top: 15px;
  padding: 5px 6px;
`;

export const SpanDownload = styled.span`
  font-size: 12px;
  color: ${({ theme }) =>
    theme?.sideCardProposal?.linkColor
      ? theme?.sideCardProposal?.linkColor
      : "#00a2ff"};
`;
