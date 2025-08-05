import React from "react";
import { Row, Col } from "react-bootstrap";
import {
  CardOtherItemBtn,
  CardOtherItemInner,
  CardOtherItemNoBorder,
  QuoteCardMain,
} from "./defaultCard/quoteCard";
import Skeleton, { SkeletonTheme } from "react-loading-skeleton";
import styled from "styled-components";
import { TypeReturn } from "modules/type";

const QuotesCardSkeleton = ({
  popupCard,
  multiPopupCard,
  lessthan767,
  quotesLoaded,
  loading,
  type,
  maxAddonsMotor,
}) => {
  const commonSkeleton = (width, height, style) => {
    return (
      <SkeletonTheme
        color="#f2f2f2"
        highlightColor="#E0E0E0"
        style={{
          ...(style &&
            lessthan767 &&
            popupCard && {
              display: "none",
            }),
        }}
      >
        <SkeletonContainer>
          <Skeleton duration={3} height={height} width={width} />
        </SkeletonContainer>
      </SkeletonTheme>
    );
  };

  return (
    <Col
      lg={!popupCard ? 4 : multiPopupCard ? 4 : 6}
      md={6}
      sm={12}
      style={{
        marginTop: !popupCard ? "30px" : "20px",
        maxWidth: popupCard ? (lessthan767 ? "100%" : "45%") : "",
        cursor: quotesLoaded || loading ? "progress" : "default",
      }}
    >
      <QuoteCardMain
        style={{
          ...(lessthan767 &&
            popupCard && {
              minHeight: "310px",
            }),
        }}
      >
        <CardOtherItemInner>
          <Row>
            <Col xlg={6} lg={6} md={6}>
              {commonSkeleton("", 54)}
            </Col>
            <Col xlg={6} lg={6} md={6}>
              <Row>
                <Col xlg={12} lg={12} md={12}>
                  {commonSkeleton("", 25, true)}
                </Col>
              </Row>
              <Row>
                <Col xlg={12} lg={12} md={12}>
                  {commonSkeleton("", 25, true)}
                </Col>
              </Row>
            </Col>
            <Col lg={12} md={12}>
              <CardOtherName>
                {commonSkeleton("", 60)}
                {commonSkeleton(60, 10)}
                {commonSkeleton("", 15)}
              </CardOtherName>
            </Col>
          </Row>
          <CardOtherIdv></CardOtherIdv>
        </CardOtherItemInner>

        <CardOtherItemNoBorder
          style={{
            marginBottom: "10px",
            marginTop: "40px",
            padding: "4px 20px 0px 20px",
          }}
        >
          <Row style={{ gap: "9px" }}>
            {TypeReturn(type) === "car" ? (
              <>
                <Col lg={12} md={12} sm={12} xs={12}>
                  {commonSkeleton("", 15)}
                </Col>
                {[...Array(maxAddonsMotor)].map((elementInArray, index) => (
                  <>
                    <Col lg={12} md={12} sm={12} xs={12}>
                      {commonSkeleton("", 15)}
                    </Col>
                  </>
                ))}
              </>
            ) : (
              <>
                <Col lg={12} md={12} sm={12} xs={12}>
                  {commonSkeleton("", 15)}
                </Col>
                {[...Array(maxAddonsMotor)].map((elementInArray, index) => (
                  <>
                    <Col lg={12} md={12} sm={12} xs={12}>
                      {commonSkeleton("", 15)}
                    </Col>
                  </>
                ))}
              </>
            )}
          </Row>
        </CardOtherItemNoBorder>
        {!popupCard && (
          <Row mb-10 style={{ marginBottom: "10px" }}>
            <Col lg={6} md={6}>
              <CardOtherItemBtn>{commonSkeleton(100, 20)}</CardOtherItemBtn>
            </Col>
            <Col lg={6} md={6}>
              <CardOtherItemBtn>{commonSkeleton(100, 20)}</CardOtherItemBtn>
            </Col>
          </Row>
        )}
      </QuoteCardMain>
    </Col>
  );
};

export default QuotesCardSkeleton;

const SkeletonContainer = styled.p`
  margin-bottom: 0;
`;

const CardOtherName = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-SemiBold"};
  font-size: 13px;
  line-height: 20px;
  margin-bottom: 4px;
  height: 20px;
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    height: 40px;
  }
`;

const CardOtherIdv = styled.div`
  float: left;
  position: relative;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 15px;
  line-height: 20px;
  color: #000;
  margin-bottom: 11px;
  cursor: pointer;
  font-weight: 600;
  height: 20px;
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    height: 30px;
  }
`;
