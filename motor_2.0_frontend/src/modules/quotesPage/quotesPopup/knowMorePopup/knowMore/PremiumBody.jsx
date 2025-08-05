import React from "react";
import { Col, Row } from "react-bootstrap";
import { Body } from "../style";
import Table from "./Tables";

const PremiumBody = ({
  lessthan993,
  quote,
  temp_data,
  type,
  prefill,
  tempData,
  addOnsAndOthers,
  totalPremiumA,
  totalPremiumB,
  totalPremiumC,
  llpaidCon,
  others,
  otherDiscounts,
  totalApplicableAddonsMotor,
  addonDiscountPercentage,
  othersList,
  revisedNcb,
  totalPremium,
  totalAddon,
  gst,
  uwLoading,
  finalPremium,
  extraLoading
}) => {
  return (
    <Body style={{ ...(lessthan993 && { display: "none" }) }}>
      <div>
        <Row>
          <Col md={6} sm={12}>
            <Table.PlanDetailsTable
              quote={quote}
              addOnsAndOthers={addOnsAndOthers}
              type={type}
              temp_data={temp_data}
              totalPremiumA={totalPremiumA}
            />
          </Col>
          <Col md={6} sm={12}>
            <Table.DiscountTable
              revisedNcb={revisedNcb}
              temp_data={temp_data}
              addOnsAndOthers={addOnsAndOthers}
              quote={quote}
              otherDiscounts={otherDiscounts}
              totalPremiumC={totalPremiumC}
            />
          </Col>
        </Row>
        <Row>
          <Col md={6} sm={12}>
            <Table.LiabilityTable
              quote={quote}
              addOnsAndOthers={addOnsAndOthers}
              temp_data={temp_data}
              type={type}
              llpaidCon={llpaidCon}
              others={others}
              othersList={othersList}
              totalPremiumB={totalPremiumB}
            />
          </Col>
          <Col md={6} sm={12}>
            <Table.AddonsTable
              totalApplicableAddonsMotor={totalApplicableAddonsMotor}
              addonDiscountPercentage={addonDiscountPercentage}
              quote={quote}
              addOnsAndOthers={addOnsAndOthers}
              lessthan993={lessthan993}
              type={type}
              others={others}
              othersList={othersList}
              totalAddon={totalAddon}
            />
          </Col>
        </Row>
        <Row style={{ marginTop: "20px" }}>
          <Col md={12} sm={12}>
            <Table.FinalCalculation
              totalPremiumA={totalPremiumA}
              quote={quote}
              totalAddon={totalAddon}
              totalPremiumC={totalPremiumC}
              totalPremiumB={totalPremiumB}
              uwLoading={uwLoading}
              totalPremium={totalPremium}
              gst={gst}
              finalPremium={finalPremium}
              extraLoading={extraLoading}
            />
          </Col>
        </Row>
      </div>
    </Body>
  );
};

export default PremiumBody;
