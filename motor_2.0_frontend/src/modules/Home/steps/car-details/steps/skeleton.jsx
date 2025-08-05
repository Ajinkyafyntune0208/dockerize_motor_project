import React from "react";
import { Row, Col } from "react-bootstrap";
import Skeleton from "react-loading-skeleton";

export const SkeletonRow = ({ count, width, height, margin, proposal }) => (
  <Row>
    {[...Array(count)].map((_, index) => (
      <Col
        key={index}
        style={{ borderRadius: "10px", marginTop: margin ? margin : "" }}
      >
        {proposal && <Skeleton width={150} height={20} />}
        <Skeleton width={width} height={height} />
      </Col>
    ))}
  </Row>
);

export const SkeletonRowsContainer = ({ count, height }) => {
  const skeletonRows = [];

  for (let i = 0; i < count; i++) {
    skeletonRows.push(<SkeletonRow key={i} count={1} height={height} />);
  }

  return <>{skeletonRows}</>;
};

// year skeleton
export const generateYearSkeletonRows = (count, width, height, margin) => {
  const skeletonRows = [];

  for (let i = 0; i < count; i++) {
    skeletonRows.push(
      <SkeletonRow
        key={i}
        count={3}
        width={width}
        height={height}
        margin={margin}
      />
    );
  }

  return skeletonRows;
};
