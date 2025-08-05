import React from "react";
import { Col, Row } from "react-bootstrap";
import Style from "./style";
import Skeleton from "react-loading-skeleton";

export const SkeletonLoader = () => {
  return (
    <Style.FilterMenuWrap>
      <Style.FilterMenuRow>
        <Row style={{ margin: "auto" }}>
          <Col lg={3} md={12}>
            <Style.FilterMenuOpenWrap>
              <Style.FilterMenuOpenTitle>
                <Skeleton width={200} height={15}></Skeleton>
              </Style.FilterMenuOpenTitle>
              <Style.FilterMenuOpenSub>
                {" "}
                <Skeleton width={200} height={15}></Skeleton>
              </Style.FilterMenuOpenSub>
            </Style.FilterMenuOpenWrap>
          </Col>

          <Col lg={3} md={12}>
            <Style.FilterMenuOpenWrap>
              <Style.FilterMenuOpenSub>
                <Skeleton width={100} height={15}></Skeleton>
                <Style.FilterMenuOpenSubBold
                  style={{ textTransform: "capitalize" }}
                >
                  <Skeleton width={100} height={15}></Skeleton>
                </Style.FilterMenuOpenSubBold>
              </Style.FilterMenuOpenSub>
              <Style.FilterMenuOpenEdit>
                <Style.FilterMenuOpenTitle>
                  <Skeleton width={100} height={15}></Skeleton>
                  <Style.FilterMenuOpenSubBold>
                    {" "}
                    <Skeleton width={100} height={15}></Skeleton>
                  </Style.FilterMenuOpenSubBold>
                </Style.FilterMenuOpenTitle>
              </Style.FilterMenuOpenEdit>
            </Style.FilterMenuOpenWrap>
          </Col>

          <Col lg={3} md={12}>
            <Style.FilterMenuOpenWrap>
              <Style.FilterMenuOpenSub>
                <Skeleton width={100} height={15}></Skeleton>
                <Style.FilterMenuOpenSubBold>
                  {" "}
                  <Skeleton width={100} height={15}></Skeleton>
                </Style.FilterMenuOpenSubBold>
              </Style.FilterMenuOpenSub>
              <Style.FilterMenuOpenEdit>
                <Style.FilterMenuOpenTitle>
                  <Skeleton width={100} height={15}></Skeleton>
                  <Style.FilterMenuOpenSubBold>
                    {" "}
                    <Skeleton width={100} height={15}></Skeleton>
                  </Style.FilterMenuOpenSubBold>
                </Style.FilterMenuOpenTitle>
              </Style.FilterMenuOpenEdit>
            </Style.FilterMenuOpenWrap>
          </Col>

          <Col lg={3} md={12}>
            <Style.FilterMenuOpenWrap>
              <Style.FilterMenuOpenSub>
                <Skeleton width={100} height={15}></Skeleton>
                <Style.FilterMenuOpenSubBold>
                  {" "}
                  <Skeleton width={100} height={15}></Skeleton>
                </Style.FilterMenuOpenSubBold>
              </Style.FilterMenuOpenSub>
              <Style.FilterMenuOpenEdit>
                <Style.FilterMenuOpenTitle>
                  <Skeleton width={100} height={15}></Skeleton>
                  <Style.FilterMenuOpenSubBold>
                    {" "}
                    <Skeleton width={100} height={15}></Skeleton>
                  </Style.FilterMenuOpenSubBold>
                </Style.FilterMenuOpenTitle>
              </Style.FilterMenuOpenEdit>
            </Style.FilterMenuOpenWrap>
          </Col>
        </Row>
      </Style.FilterMenuRow>
    </Style.FilterMenuWrap>
  );
};
