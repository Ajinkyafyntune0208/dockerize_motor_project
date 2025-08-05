import {
  Textbox,
  ContactFn,
  FormWrapper,
  FormGroup,
  brokerEmailFunction,
} from "components";
import React from "react";
import Style from "./style";
import { Col, Row } from "react-bootstrap";

export const Content2 = () => {
  return (
    <Style.MessageContainer>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="#4fcc6e"
        width="48px"
        height="48px"
      >
        <path d="M0 0h24v24H0z" fill="none"></path>
        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path>
      </svg>
      <Style.CallText>
        One of our experts will get back to you within few minutes
      </Style.CallText>
    </Style.MessageContainer>
  );
};

export const Content1 = ({
  handleSubmit,
  onSubmit,
  register,
  errors,
  watch,
  lessthan414,
  theme_conf,
}) => {
  return (
    <Style.Content1>
      <Style.Heading>NEED HELP?</Style.Heading>
      <Style.ContactText>
        Submit your details and we'll reach out supersoon!
      </Style.ContactText>

      <form
        style={{ display: "flex", justifyContent: "center" }}
        onSubmit={handleSubmit(onSubmit)}
      >
        <FormWrapper
          style={{
            justifyContent: "center",
            flexDirection: "column",
            alignItems: "center",
            width: "80%",
          }}
        >
          <div className="w-100">
            <Textbox
              nonCircular
              md
              type="text"
              id="name"
              fieldName="Name"
              onInput={(e) =>
                (e.target.value =
                  e.target.value.length <= 1
                    ? ("" + e.target.value).toUpperCase()
                    : e.target.value)
              }
              name="name"
              placeholder=" "
              register={register}
              error={errors?.name}
              fontWeight="bold"
            />
          </div>
          <div className="w-100">
            <Textbox
              style={{ marginBottom: "20px" }}
              nonCircular
              md
              type="tel"
              id="mobileNo"
              fieldName="Mobile No."
              name="mobileNo"
              placeholder=" "
              register={register}
              error={errors?.mobileNo}
              maxLength="10"
              fontWeight="bold"
              onInput={(e) =>
                (e.target.value = e.target.value.replace(/[^0-9-/]/g, ""))
              }
            />
          </div>
          <div className="w-100">
            <Textbox
              nonCircular
              md
              type="email"
              id="email"
              fieldName="Email"
              name="email"
              placeholder=" "
              register={register}
              error={errors?.email}
              fontWeight="bold"
            />
          </div>
          <FormGroup
            style={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center",
              minHeight: "70px",
              width: "90%",
              flexDirection: "column",
            }}
            md
          >
            <Style.Button
              type="submit"
              disabled={
                (!watch("email") && !watch("mobileNo")) ||
                errors?.name ||
                (watch("email") && errors?.email) ||
                (watch("mobileNo") && errors?.mobileNo)
              }
              style={{
                width: lessthan414 && "80%",
                height: "40px",
                marginBottom: "15px",
              }}
            >
              {import.meta.env?.VITE_BROKER === "SRIDHAR"
                ? "Call Me"
                : "Contact Me"}
            </Style.Button>
            {import.meta.env.VITE_BROKER !== "UIB" && (
              <>
                {" "}
                <Style.ORLine>OR</Style.ORLine>
                <h4
                  style={{ display: "block", fontSize: "1rem" }}
                  className="text-secondary"
                >
                  For immediate assistance call us at
                </h4>
                <p>
                  <b style={{ color: "black", fontSize: "16px" }}>
                    {theme_conf?.broker_config?.phone || ContactFn()}
                  </b>
                </p>
              </>
            )}
            <Style.ORLine>OR</Style.ORLine>
            <h4
              style={{ display: "block", fontSize: "1rem" }}
              className="text-secondary"
            >
              {import.meta.env.VITE_BROKER === "UIB"
                ? "For immediate assistance email us at"
                : "You can reach us at"}
            </h4>
            <p>
              <b
                style={{
                  color:
                    import.meta.env.VITE_BROKER === "UIB"
                      ? "#0067b1"
                      : "black",
                  fontSize: "16px",
                }}
              >
                {theme_conf?.broker_config?.email || brokerEmailFunction()}
              </b>
            </p>
          </FormGroup>
        </FormWrapper>
      </form>
    </Style.Content1>
  );
};

export const Content3 = ({ Theme, theme_conf }) => {
  return (
    <Style.Content1>
      <Row className="d-flex flex-wrap w-100">
        <Col
          sm="2"
          md="2"
          lg="2"
          xl="2"
          className="d-flex justify-content-center align-content-center w-100 h-100"
        >
          <h1 className="w-100 h-100 p-1 text-center">
            <i
              className="fa fa-phone fa-flip-horizontal"
              style={{
                color: Theme?.CallUs?.icon ? Theme?.CallUs?.icon : "#28a745",
              }}
            ></i>
          </h1>
        </Col>
        <Col
          sm="10"
          md="10"
          lg="10"
          xl="10"
          className="d-flex justify-content-center align-content-center w-100"
        >
          <h4 className="text-secondary">
            Feel free to call us on our no.{" "}
            <b style={{ color: "black" }}>
              {theme_conf?.broker_config?.phone || ContactFn()}
            </b>
          </h4>
        </Col>
      </Row>
    </Style.Content1>
  );
};
