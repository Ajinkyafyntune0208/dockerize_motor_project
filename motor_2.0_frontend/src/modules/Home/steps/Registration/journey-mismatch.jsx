import React from "react";
import { Row, Col, Modal } from "react-bootstrap";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";
import { Button } from "components";
import { useMediaPredicate } from "react-media-hook";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const JourneyMismatch = (props) => {
  const lessthan768 = useMediaPredicate("(max-width: 768px)");
  const redirections =
    props?.frontendurl &&
    !_.isEmpty(props?.frontendurl) &&
    _.compact(Object.values(props?.frontendurl)).length > 1;
    
  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
    >
      <Modal.Body>
        <div style={{ padding: "24px" }}>
          <Row style={{ width: "100%", margin: "0" }}>
            <Col xs={1} className="p-0 m-0 text-center">
              <img
                src={`${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/thinking.png`}
                style={{ width: "50%" }}
                alt="thinking_image"
              />
            </Col>
            <Col
              xs={11}
              style={{ padding: "0", display: "flex", alignItems: "center" }}
            >
              <h5 style={{ margin: "0" }}>
                {props?.Renewal
                  ? `Vehicle class mismatch. Policy cannot be renewed online for this vehicle.`
                  : `It Looks like you have entered a ${props?.show}'s registration number. Do you want
              to ..`}
              </h5>
            </Col>
          </Row>
        </div>
      </Modal.Body>
      <Modal.Footer>
        {!["gcv", "pcv"].includes(props?.show) && (
          <Button
            type="button"
            buttonStyle="outline-solid"
            onClick={() => {
              return [
                props?.setValue("regNo", ""),
                props?.setValue("policyNo", ""),
                props?.clearFastLane(),
                props?.onHide(),
              ];
            }}
            hex1={
              Theme?.paymentConfirmation?.Button?.hex1
                ? Theme?.paymentConfirmation?.Button?.hex1
                : "#4ca729"
            }
            hex2={
              Theme?.paymentConfirmation?.Button?.hex2
                ? Theme?.paymentConfirmation?.Button?.hex2
                : "#4ca729"
            }
            borderRadius="5px"
            color={
              Theme?.PaymentConfirmation?.buttonTextColor
                ? Theme?.PaymentConfirmation?.buttonTextColor
                : "white"
            }
            style={{ ...(lessthan768 && { width: "100%" }) }}
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {"Enter New Number"}
            </text>
          </Button>
        )}
        {!props?.Renewal && redirections && (
          <Button
            type="submit"
            buttonStyle="outline-solid"
            onClick={() => props?.journeyChange()}
            hex1={
              Theme?.paymentConfirmation?.Button?.hex1
                ? Theme?.paymentConfirmation?.Button?.hex1
                : "#4ca729"
            }
            hex2={
              Theme?.paymentConfirmation?.Button?.hex2
                ? Theme?.paymentConfirmation?.Button?.hex2
                : "#4ca729"
            }
            borderRadius="5px"
            color={
              Theme?.PaymentConfirmation?.buttonTextColor
                ? Theme?.PaymentConfirmation?.buttonTextColor
                : "white"
            }
            style={{ ...(lessthan768 && { width: "100%" }) }}
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {`Continue with ${props?.show} insurance`}
              <i className="fa fa-arrow-circle-right ml-2"></i>
            </text>
          </Button>
        )}
      </Modal.Footer>
    </Modal>
  );
};

export default JourneyMismatch;
