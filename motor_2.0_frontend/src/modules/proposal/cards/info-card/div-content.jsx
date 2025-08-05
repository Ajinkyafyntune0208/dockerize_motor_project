import React from "react";
import { DivContent } from "./info-style";
import { Badge, Col, Row } from "react-bootstrap";
import PlanDetails from "./component/plan-details";
import VehicleDetail from "./component/vehicle-details";
import PBSection from "./component/pb-section";
import { TypeReturn } from "modules/type";
import _ from "lodash";
import { _haptics } from "utils";

const DivisionContent = ({
  breakinCase,
  type,
  icr,
  Theme,
  redirectTo,
  selectedQuote,
  quoteLog,
  temp_data,
  VehicleDetails,
  lessthan767,
  showBreakup,
  showVehicleInfo,
  vehicleInfo,
  breakup,
}) => {
  return (
    <DivContent>
      <Row>
        <Col className="m-0 p-0 d-flex justify-content-end">
          {(!breakinCase ||
            (TypeReturn(type) === "bike" &&
              breakinCase &&
              selectedQuote?.companyAlias !== "godigit" &&
              selectedQuote?.companyAlias !== "icici_lombard" &&
              selectedQuote?.companyAlias !== "united_india")) &&
            !icr && (
              <Badge
                variant={
                  Theme?.sideCardProposal?.editBadge
                    ? Theme?.sideCardProposal?.editBadge
                    : "dark"
                }
                style={{
                  cursor: "pointer",
                }}
                id="change-insurer"
                onClick={() => [_haptics([100, 0, 50]), redirectTo()]}
              >
                {"Change Insurer"}
              </Badge>
            )}
        </Col>
      </Row>
      <PlanDetails
        selectedQuote={selectedQuote}
        quoteLog={quoteLog}
        type={type}
        temp_data={temp_data}
      />
      {!_.isEmpty(VehicleDetails) && (
        <VehicleDetail
          lessthan767={lessthan767}
          showVehicleInfo={showVehicleInfo}
          vehicleInfo={vehicleInfo}
          type={type}
          temp_data={temp_data}
          VehicleDetails={VehicleDetails}
        />
      )}
      <PBSection
        lessthan767={lessthan767}
        showBreakup={showBreakup}
        breakup={breakup}
        quoteLog={quoteLog}
        temp_data={temp_data}
      />
    </DivContent>
  );
};

export default DivisionContent;
