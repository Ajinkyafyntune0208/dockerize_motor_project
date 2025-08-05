import React, { Fragment } from "react";
import { ErrorMsg } from "components";
import { getNewNcb } from "modules/proposal/proposal-constants";
import { FormGroupTag } from "modules/proposal/style";
import { Form, Col } from "react-bootstrap";

const ClaimDetails = ({
  register,
  allFieldsReadOnly,
  temp_data,
  CardData,
  watch,
  prepolicy,
  errors,
}) => {
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2 fname">
          <FormGroupTag mandatory>Claim Details</FormGroupTag>
          <Form.Control
            autoComplete="off"
            as="select"
            size="sm"
            ref={register}
            placeholder="Claim Details"
            readOnly={allFieldsReadOnly}
            name={`isClaim`}
            style={{ cursor: "pointer" }}
          >
            <option
              selected={
                (temp_data?.corporateVehiclesQuoteRequest?.isClaim === "Y" &&
                  !CardData?.prepolicy?.isClaim) ||
                CardData?.prepolicy?.isClaim === "Y"
              }
              value={"Y"}
            >
              YES
            </option>
            <option
              selected={
                (temp_data?.corporateVehiclesQuoteRequest?.isClaim === "N" &&
                  !CardData?.prepolicy?.isClaim) ||
                CardData?.prepolicy?.isClaim === "N"
              }
              value={"N"}
            >
              NO
            </option>
          </Form.Control>
        </div>
        <input
          ref={register}
          name={"claim"}
          value={watch("isClaim") === "Y" ? "YES" : "NO" || ""}
          type="hidden"
        />
      </Col>
      {watch("isClaim") !== "Y" && !temp_data?.carOwnership ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2 fname">
            <FormGroupTag mandatory>No Claim Bonus</FormGroupTag>
            <Form.Control
              autoComplete="off"
              as="select"
              size="sm"
              ref={register}
              placeholder="Claim Details"
              readOnly={allFieldsReadOnly}
              name={`previousNcb`}
              id="ncbPre"
              style={{ cursor: "pointer" }}
            >
              <Fragment id="ncbPreOpt">
                {!CardData?.prepolicy?.previousNcb &&
                  !prepolicy?.previousNcb && (
                    <option value={"@"}>Select</option>
                  )}
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      0 &&
                      !CardData?.prepolicy?.previousNcb * 1 !== 0) ||
                    CardData?.prepolicy?.previousNcb === 0
                  }
                  value={"0"}
                >
                  0%
                </option>
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      20 &&
                      !CardData?.prepolicy?.previousNcb * 1 !== 20) ||
                    CardData?.prepolicy?.previousNcb * 1 === 20
                  }
                  value={"20"}
                >
                  20%
                </option>
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      25 &&
                      !CardData?.prepolicy?.previousNcb * 1 !== 25) ||
                    CardData?.prepolicy?.previousNcb * 1 === 25
                  }
                  value={"25"}
                >
                  25%
                </option>
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      35 &&
                      !CardData?.prepolicy?.previousNcb * 1 !== 35) ||
                    CardData?.prepolicy?.previousNcb * 1 === 35
                  }
                  value={"35"}
                >
                  35%
                </option>
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      45 &&
                      CardData?.prepolicy?.previousNcb * 1 !== 45) ||
                    CardData?.prepolicy?.previousNcb * 1 === 45
                  }
                  value={"45"}
                >
                  45%
                </option>
                <option
                  selected={
                    (temp_data?.corporateVehiclesQuoteRequest?.previousNcb *
                      1 ===
                      50 &&
                      !CardData?.prepolicy?.previousNcb * 1 !== 50) ||
                    CardData?.prepolicy?.previousNcb * 1 === 50
                  }
                  value={"50"}
                >
                  50%
                </option>
              </Fragment>{" "}
              || <></>
            </Form.Control>
            {!!errors?.previousNcb ? (
              <ErrorMsg fontSize={"10px"}>
                {errors?.previousNcb?.message}
              </ErrorMsg>
            ) : (
              <Form.Text className="text-muted">
                <text style={{ color: "#bdbdbd" }}>
                  (Incorrect declaration of NCB might lead to NCB recovery or
                  decline of claim)
                </text>
              </Form.Text>
            )}
          </div>
          <input
            type="hidden"
            name="applicableNcb"
            ref={register}
            value={
              temp_data?.prevShortTerm * 1
                ? watch("previousNcb")
                  ? watch("previousNcb")
                  : "0"
                : getNewNcb(watch("previousNcb") ? watch("previousNcb") : "0")
            }
          />
        </Col>
      ) : (
        <>
          <input
            type="hidden"
            name="previousNcb"
            ref={register}
            value={
              temp_data?.corporateVehiclesQuoteRequest?.previousPolicyType ===
              "Third-party"
                ? "0"
                : temp_data?.corporateVehiclesQuoteRequest?.previousNcb
            }
          />
          <input
            type="hidden"
            name="applicableNcb"
            ref={register}
            value={"0"}
          />
        </>
      )}
    </>
  );
};

export default ClaimDetails;
