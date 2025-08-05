import { IconlessCard, CompactCard as Card } from "components";
import { SkeletonRow } from "modules/Home/steps/car-details/steps/skeleton";
import React from "react";
import { Col, Row } from "react-bootstrap";
import Skeleton from "react-loading-skeleton";

const ProposalSkeleton = () => {
  return (
    <Row style={{ margin: "15px 60px 20px 30px" }}>
      <Col md={4} lg={4} xl={4}>
        <IconlessCard headerStyle={{ display: "none" }} marginTop={"0px"}>
          <div>
            <Skeleton height={80} width={150} className="mb-3" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
          </div>
          <div style={{ marginTop: "30px" }}>
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
          </div>
          <div style={{ marginTop: "30px" }}>
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
            <Skeleton className="my-2" />
          </div>
          <Skeleton className="my-2" height={100} />
        </IconlessCard>
      </Col>
      <Col md={8} lg={8} xl={8}>
        <Card headerStyle={{ display: "none" }} marginTop={"0px"}>
          <Skeleton height={50} />
          <div className="mt-3">
            <SkeletonRow margin={"15px"} count={3} height={50} proposal />
            <SkeletonRow margin={"15px"} count={3} height={50} proposal />
            <SkeletonRow margin={"15px"} count={3} height={50} proposal />
            <SkeletonRow margin={"15px"} count={3} height={50} proposal />
            <SkeletonRow margin={"15px"} count={1} height={100} proposal />
            <SkeletonRow margin={"15px"} count={3} height={50} proposal />
          </div>

          <div
            style={{
              display: "flex",
              justifyContent: "center",
              margin: "30px 0 20px 0",
            }}
          >
            <Skeleton height={50} width={250} />
          </div>
        </Card>
        <Card marginTop={"0px"} headerStyle={{ display: "none" }}>
          <Skeleton height={50} />
        </Card>
        <Card marginTop={"0px"} headerStyle={{ display: "none" }}>
          <Skeleton height={50} />
        </Card>
      </Col>
    </Row>
  );
};

export default ProposalSkeleton;
